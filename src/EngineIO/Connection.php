<?php

namespace SwooleIO\EngineIO;

use OpenSwoole\Http\Response;
use OpenSwoole\Timer;
use Psr\Http\Message\ServerRequestInterface;
use SwooleIO\Constants\ConnectionStatus;
use SwooleIO\Constants\EioPacketType;
use SwooleIO\Constants\SioPacketType;
use SwooleIO\Constants\Transport;
use SwooleIO\SocketIO\Nsp;
use SwooleIO\SocketIO\Packet as SioPacket;
use SwooleIO\SocketIO\Socket;
use function SwooleIO\io;

class Connection
{

    public static int $pingTimeout = 20000;
    public static int $pingInterval = 25000;
    /** @var Connection[] */
    protected static array $Connections = [];
    public ?int $writable = null;
    public array $timers;
    protected ServerRequestInterface $request;
    protected int $fd = -1;
    protected Transport $transport = Transport::polling;
    /** @var string[] $buffer */
    protected array $buffer = [];
    /** @var Socket[] */
    protected array $sockets = [];
    protected string $pid;
    protected ConnectionStatus $status = ConnectionStatus::disconnected;
    protected ?Transport $upgrade;

    public function __construct(protected string $sid)
    {
        $this->pid = $this->sid;
    }

    public static function bySid(string $sid): ?Connection
    {
        return self::recover($sid);
    }

    public static function recover(string $sid): ?Connection
    {
        if (isset(self::$Connections[$sid]))
            return self::$Connections[$sid];
        $socket = self::fetch($sid);
        if (isset($socket)) self::$Connections[$sid] = $socket;
        return $socket;
    }

    public static function fetch(string $sid): ?self
    {
        return io()->table('sid')->get($sid, 'sock');
    }

    public static function byPid(string $pid): ?Connection
    {
        return self::recover(io()->table('pid')->get($pid, 'sid') ?? '');
    }

    public static function byFd(int $fd): ?Connection
    {
        return self::recover(io()->table('fd')->get($fd, 'sid') ?? '');
    }

    public static function saveAll(): void
    {
        foreach (self::$Connections as $socket)
            $socket->save();
    }

    public function save(bool $socket = false): self
    {
        $io = io();
        $worker = $io->server()->getWorkerId();
        $save = ['transport' => $this->transport->value, 'worker' => $worker];
        $sid = ['sid' => $this->sid, 'worker' => $worker];
        if ($this->fd)
            $io->table('fd')->set($this->fd, $sid);
        if ($socket) $save['sock'] = $this;
        $io->table('sid')->set($this->sid, $save);
        $io->table('pid')->set($this->pid, $sid);
        return $this;
    }

    public function is(ConnectionStatus ...$status): bool
    {
        return in_array($this->status, $status);
    }

    public function status(): ConnectionStatus
    {
        return $this->status;
    }

    public function sid(string $sid = null): string|Connection
    {
        if (!isset($sid)) return $this->sid;
        if ($sid != $this->sid) {
            io()->table('sid')->del($this->sid);
            $this->sid = $sid;
            $this->save();
        }
        return $this;
    }

    public function request(ServerRequestInterface $request = null): ServerRequestInterface|Connection
    {
        if (!isset($request)) return $this->request;
        $this->request = $request;
        return $this;
    }

    public function receive(SioPacket $packet): void
    {
        $io = io();
        $server = $io->server();
        switch ($packet->getEngineType()) {
            case EioPacketType::close:
                $this->status = ConnectionStatus::closing;
                $this->disconnect();
                $this->status = ConnectionStatus::closed;
                break;
            case EioPacketType::ping:
                $payload = $packet->getPayload();
                $pong = Packet::create(EioPacketType::pong, $payload);
                if ($this->status == ConnectionStatus::connected && $payload == 'probe') {
                    $this->upgrading(Transport::websocket);
                    if ($this->upgrade == Transport::websocket)
                        $server->push($this->fd, $pong->encode());
                } else
                    $this->push($pong);
                break;
            case EioPacketType::pong:
                $this->resetTimeout();
                break;
            case EioPacketType::message:
                $nsp = $packet->getNamespace();
                if ($packet->getSocketType() == SioPacketType::connect) {
                    if (!isset($this->sockets[$nsp])) {
                        $socket = Socket::create($this, $nsp);
                        if (Nsp::exists($nsp)) {
                            Nsp::get($nsp)->connect($socket, $packet);
                            $socket->emitReserved(SioPacketType::connect, ['sid' => $socket->cid()]);
                        } else
                            $socket->emitReserved(SioPacketType::connect_error, ['message' => 'Invalid Namespace']);
                        $this->sockets[$nsp] = $socket;
                    } else break;
                } elseif (isset($this->sockets[$nsp])) $socket = $this->sockets[$nsp];
                else break;
                $socket->receive($packet);
                break;
            case EioPacketType::upgrade:
                $this->transport($this->upgrade);
                $this->upgrade = null;
                $this->status = ConnectionStatus::upgraded;
                break;
        }
    }

    public function disconnect(string $reason = ''): void
    {
        foreach ($this->sockets as $connection)
            $connection->close();
        unset(self::$Connections[$this->fd]);
        foreach ($this->timers as $timer)
            Timer::clear($timer);
        $server = io()->server();
        if ($server->isEstablished($this->fd))
            $server->disconnect($this->fd, reason: $reason);
    }

    public function close(string $namespace): void
    {
        unset($this->sockets[$namespace]);
    }

    public static function create(string $sid, Transport $transport = Transport::polling): Connection
    {
        $connection = new Connection($sid);
        $connection->status = ConnectionStatus::connected;
        $connection->timers['ping'] = Timer::tick(Connection::$pingInterval, fn($t, $packet) => $connection->push($packet), Packet::create(EioPacketType::ping));
        $connection->resetTimeout();
        if (isset($transport)) $connection->transport($transport)->save();
        return self::$Connections[$sid] = $connection;
    }

    public function push(Packet $packet): bool
    {
        $this->buffer[] = $packet->encode(true);
        return $this->flush();
    }

    public function flush(): bool
    {
        if (isset($this->upgrade)) return false;
        $server = io()->server();
        switch ($this->transport) {
            case Transport::polling:
                if (isset($this->writable) && $this->buffer) {
                    $response = Response::create($this->writable);
                    if ($response->write(implode(chr(30), $this->buffer)))
                        $this->buffer = [];
                    $response->end();
                    $this->writable = null;
                }
                return true;
            case Transport::websocket:
                if ($this->isConnected())
                    foreach ($this->buffer as $key => $data) {
                        if ($server->push($this->fd, $data))
                            unset($this->buffer[$key]);
                        else
                            return false;
                    }
                return true;
            default:
                return false;
        }
    }

    public function isConnected(): bool
    {
        $io = io();
        if ($this->status == ConnectionStatus::upgraded && $this->fd && $io->server()->isEstablished($this->fd))
            return true;
        return false;
    }

    protected function resetTimeout(): int|bool
    {
        if ($timer_id = &$this->timers['timeout'])
            Timer::clear($timer_id);
        return $timer_id = Timer::after(Connection::$pingTimeout, fn() => $this->disconnect('ping timeout'));
    }

    public function transport(Transport $transport = null): Transport|Connection
    {
        if (!isset($transport)) return $this->transport;
        if ($transport != $this->transport)
            $this->transport = $transport;
        return $this;
    }

    public function upgrading(Transport $transport): self
    {
        $this->upgrade = $transport;
        $this->status = ConnectionStatus::upgrading;
        return $this;
    }

    public static function connect(string $sid): Connection
    {
        return self::recover($sid) ?? self::create($sid);
    }

    public function fd(int $fd = null): int|Connection
    {
        $io = io();
        if (!isset($fd)) return $this->fd;
        if ($fd != $this->fd) {
            $io->table('fd')->del($this->fd);
            $this->fd = $fd;
            $io->table('fd')->set($this->fd, ['sid' => $this->sid, 'worker' => $io->server()->getWorkerId()]);
        }
        return $this;
    }

    public function pid(): string
    {
        return $this->pid;
    }

    public function socket(string $nsp): ?Socket
    {
        return $this->sockets[$nsp]?? null;
    }

}