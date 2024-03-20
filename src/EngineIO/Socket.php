<?php

namespace SwooleIO\EngineIO;

use OpenSwoole\Http\Response;
use OpenSwoole\Timer;
use Psr\Http\Message\ServerRequestInterface;
use SwooleIO\Constants\SocketStatus;
use SwooleIO\Constants\Transport;
use SwooleIO\SocketIO\Connection;
use SwooleIO\SocketIO\Packet as SioPacket;
use function SwooleIO\debug;
use function SwooleIO\io;

class Socket
{

    public static int $pingTimeout = 20000;
    public static int $pingInterval = 6000;
    /** @var Socket[] */
    protected static array $Sockets = [];
    public ?int $writable = null;
    protected ServerRequestInterface $request;
    protected int $fd = -1;
    protected Transport $transport = Transport::polling;

    /** @var string[] $buffer */
    protected array $buffer = [];
    /** @var Connection[] */
    protected array $connections = [];
    protected string $pid;
    protected SocketStatus $status = SocketStatus::disconnected;
    protected ?Transport $upgrade;
    protected array $timers;

    public function __construct(protected string $sid)
    {
        $this->pid = $this->sid;
    }

    public static function bySid(string $sid): ?Socket
    {
        return self::recover($sid);
    }

    public static function recover(string $sid): ?Socket
    {
        if (isset(self::$Sockets[$sid]))
            return self::$Sockets[$sid];
        $socket = self::fetch($sid);
        if (isset($socket)) self::$Sockets[$sid] = $socket;
        return $socket;
    }

    public static function fetch(string $sid): ?self
    {
        return io()->table('sid')->get($sid, 'sock');
    }

    public static function byPid(string $pid): ?Socket
    {
        return self::recover(io()->table('pid')->get($pid, 'sid') ?? '');
    }

    public static function byFd(int $fd): ?Socket
    {
        return self::recover(io()->table('fd')->get($fd, 'sid') ?? '');
    }

    public static function connect(string $sid): Socket
    {
        return self::recover($sid) ?? self::create($sid);
    }

    public static function create(string $sid, Transport $transport = Transport::polling): Socket
    {
        $socket = new Socket($sid);
        $socket->status = SocketStatus::connected;
        $ping = Packet::create('ping');
        var_dump(spl_object_id($socket));
        $socket->timers['ping'] = Timer::tick(Socket::$pingInterval, fn() => $socket->push($ping));
        $socket->resetTimeout();
        if (isset($transport)) $socket->transport($transport)->save();
        return self::$Sockets[$sid] = $socket;
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
                if ($this->buffer && $this->buffer[0] == '2') var_dump($this->writable, $this->buffer);
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
        if ($this->status == SocketStatus::upgraded && $this->fd && $io->server()->isEstablished($this->fd))
            return true;
        return false;
    }

    protected function resetTimeout(): int|bool
    {
        if ($timer_id = &$this->timers['timeout'])
            Timer::clear($timer_id);
        return $timer_id = Timer::after(Socket::$pingTimeout, fn() => $this->disconnect('ping timeout'));
    }

    public function disconnect(string $reason = ''): bool
    {
        foreach ($this->connections as $connection)
            $connection->close();
        unset(self::$Sockets[$this->fd]);
        return $this->fd == -1 || io()->server()->disconnect($this->fd, reason: $reason);
    }

    public function close(string $namespace): void
    {
        unset($this->connections[$namespace]);
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

    public function transport(Transport $transport = null): Transport|Socket
    {
        if (!isset($transport)) return $this->transport;
        if ($transport != $this->transport)
            $this->transport = $transport;
        return $this;
    }

    public static function saveAll(): void
    {
        foreach (self::$Sockets as $socket)
            $socket->save();
    }

    public function is(SocketStatus ...$status): bool
    {
        return in_array($this->status, $status);
    }

    public function status(): SocketStatus
    {
        return $this->status;
    }

    public function sid(string $sid = null): string|Socket
    {
        if (!isset($sid)) return $this->sid;
        if ($sid != $this->sid) {
            io()->table('sid')->del($this->sid);
            $this->sid = $sid;
            $this->save();
        }
        return $this;
    }

    public function request(ServerRequestInterface $request = null): ServerRequestInterface|Socket
    {
        if (!isset($request)) return $this->request;
        $this->request = $request;
        return $this;
    }

    public function receive(SioPacket $packet): void
    {
        $io = io();
        $server = $io->server();
        switch ($packet->getEngineType(1)) {
            case 1:
                $this->status = SocketStatus::closing;
                $this->disconnect();
                $this->status = SocketStatus::closed;
                break;
            case 2:
                $payload = $packet->getPayload();
                $pong = Packet::create('pong', $payload);
                if ($this->status == SocketStatus::connected && $payload == 'probe') {
                    $this->upgrading(Transport::websocket);
                    if ($this->upgrade == Transport::websocket)
                        $server->push($this->fd, $pong->encode());
                } else
                    $this->push($pong);
                break;
            case 3:
                $this->resetTimeout();
                break;
            case 4:
                $nsp = $packet->getNamespace();
                $connection = &$this->connections[$nsp];
                if (!isset($connection))
                    $connection = Connection::create($this, $nsp);
                $connection->receive($packet);
                break;
            case 5:
                $this->transport($this->upgrade);
                $this->upgrade = null;
                $this->status = SocketStatus::upgraded;
                break;
        }
    }

    public function upgrading(Transport $transport): self
    {
        $this->upgrade = $transport;
        $this->status = SocketStatus::upgrading;
        return $this;
    }

    public function fd(int $fd = null): int|Socket
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

}