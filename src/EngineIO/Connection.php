<?php

namespace SwooleIO\EngineIO;

use OpenSwoole\Core\Psr\ServerRequest;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Timer;
use Psr\Http\Message\ServerRequestInterface;
use SwooleIO\Constants\ConnectionStatus;
use SwooleIO\Constants\EioPacketType;
use SwooleIO\Constants\SioPacketType;
use SwooleIO\Constants\Transport;
use SwooleIO\EngineIO\Packet as EioPacket;
use SwooleIO\Exceptions\ConnectionError;
use SwooleIO\SocketIO\Nsp;
use SwooleIO\SocketIO\Packet as SioPacket;
use SwooleIO\SocketIO\Socket;
use function SwooleIO\io;

class Connection
{

    public static int $pingTimeout = 7000;
    public static int $pingInterval = 10000;
    /** @var Connection[] */
    protected static array $Connections = [];
    public ?int $writable = null;
    public array $timers;
    protected mixed $auth = '';
    protected ServerRequestInterface $request;
    protected int $fd = -1;
    protected Transport $transport = Transport::polling;
    /** @var string[] $buffer */
    protected array $buffer = [];
    /** @var Socket[] */
    protected array $sockets = [];
    protected string $pid, $ip = '[null]', $ua = '[null]';
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

    public function request(Request $request = null): ServerRequestInterface|Connection
    {
        if (!isset($request)) return $this->request;
        $this->request = ServerRequest::from($request);
        $this->ua = $request->header['user-agent'] ?? '[null]';
        $this->ip = explode(',', $request->header['x-forwarded-for']??'')[0]?? $request->server['remote_addr']?? '[null]';
        return $this;
    }

    public function receive(SioPacket $packet): void
    {
        $io = io();
        $server = $io->server();
        if ($this->transport() == Transport::polling)
            $this->resetTimeout();
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
                    if ($this->writable)
                        $this->flush();
                } else
                    $this->push($pong);
                break;
            case EioPacketType::pong:
                $this->clearTimeout('pong');
                break;
            case EioPacketType::message:
                $nsp = $packet->getNamespace();
                if ($packet->getSocketType() == SioPacketType::connect) {
                    if (!isset($this->sockets[$nsp])) {
                        $socket = Socket::create($this, $nsp, $packet->getData() ?: null);
                        try {
                            if (!Nsp::exists($nsp))
                                throw new ConnectionError('Invalid Namespace');
                            Nsp::get($nsp)->connect($socket, $packet);
                            $socket->emitReserved(SioPacketType::connect, ['sid' => $socket->cid()]);
                        } catch (ConnectionError $e) {
                            $socket->emitReserved(SioPacketType::connect_error, ['message' => $e->getMessage()]);
                        }
                        $this->sockets[$nsp] = $socket;
                    } else break;
                } elseif (isset($this->sockets[$nsp])) $socket = $this->sockets[$nsp];
                else break;
                $socket->receive($packet);
                break;
            case EioPacketType::upgrade:
                $this->transport($this->upgrade);
                $this->clearTimeout('timeout');
                $this->upgrade = null;
                $this->status = ConnectionStatus::upgraded;
                $this->flush();
                break;
        }
    }

    public function transport(Transport $transport = null): Transport|Connection
    {
        if (!isset($transport)) return $this->transport;
        if ($transport != $this->transport)
            $this->transport = $transport;
        return $this;
    }

    public function resetTimeout(): int|bool
    {
        $this->clearTimeout('timeout');
        return $this->timers['timeout'] = Timer::after(self::$pingTimeout + self::$pingInterval + 1000, fn() => $this->disconnect('Timed out'));
    }

    protected function clearTimeout(string $name): void
    {
        if (isset($this->timers[$name])) {
            Timer::clear($this->timers[$name]);
            unset($this->timers[$name]);
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
        $this->status = ConnectionStatus::closed;
    }

    public function close(string $namespace): void
    {
        unset($this->sockets[$namespace]);
    }

    public static function create(string $sid, Transport $transport = Transport::polling): Connection
    {
        $connection = new Connection($sid);
        $connection->status = ConnectionStatus::connected;
        $connection->timers['ping'] = Timer::tick(Connection::$pingInterval, fn($t, $packet) => $connection->push($packet) && $connection->resetPingTimeout(), Packet::create(EioPacketType::ping));
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
        if (isset($this->upgrade) && $this->writable) {
            $response = Response::create($this->writable);
            $response->write(EioPacket::create(EioPacketType::noop));
            $response->end();
            $this->writable = null;
        }
        $server = io()->server();
        switch ($this->transport) {
            case Transport::polling:
                if (isset($this->writable) && $this->buffer) {
                    $response = Response::create($this->writable);
                    if ($response->write(implode(chr(30), $this->buffer)))
                        $this->buffer = [];
                    $response->end();
                    $this->resetTimeout();
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

    protected function resetPingTimeout(): int|bool
    {
        $this->clearTimeout('pong');
        return $this->timers['pong'] = Timer::after(Connection::$pingTimeout, fn() => $this->disconnect('ping timeout'));
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

    /**
     * @template Auth of string|object|array|null
     * @param Auth $auth
     * @return (Auth is null? Auth: Connection)
     */
    public function auth(string|object|array $auth = null): string|object|array
    {
        if (!isset($auth)) return $this->auth;
        $this->auth = $auth;
        return $this;
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
        return $this->sockets[$nsp] ?? null;
    }

    public function closing(): void
    {
        $this->status = ConnectionStatus::closing;
        $this->resetTimeout();
    }

    public function ip(): string
    {
        return $this->ip;
    }

    public function ua(): string
    {
        return $this->ua;
    }

}