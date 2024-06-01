<?php

namespace SwooleIO\EngineIO;

use OpenSwoole\Core\Psr\ServerRequest;
use OpenSwoole\Coroutine;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
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
use SwooleIO\Time\TimeManager;
use SwooleIO\Time\Timer;
use function SwooleIO\io;

class Connection
{

    public static int $pingTimeout = 4000;
    public static int $pingInterval = 5000;
    /** @var Connection[] */
    protected static array $Connections = [];
    public ?int $writable = null;
    public TimeManager $timers;
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
    private bool $hold = false;
    private array $coroutines = [];

    public function __construct(protected string $sid)
    {
        $this->timers = new TimeManager();
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

    public static function create(string $sid, Transport $transport = Transport::polling): Connection
    {
        $connection = new Connection($sid);
        $connection->status = ConnectionStatus::connected;
        $packet = Packet::create(EioPacketType::ping);
        $connection->timers->tick('ping', Connection::$pingInterval / 1000, fn() => $connection->push($packet) && $connection->resetPingTimeout());
        if (isset($transport)) $connection->transport($transport)->save();
        return self::$Connections[$sid] = $connection;
    }

    public static function connect(string $sid): Connection
    {
        return self::recover($sid) ?? self::create($sid);
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
        $this->ip = $request->header['x-real-ip'] ?? explode(',', $request->header['x-forwarded-for'] ?? '')[0] ?: preg_replace('/(^|.*?:)(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/', '$2', $request->server['remote_addr']) ?? '[null]';
        return $this;
    }

    public function receive(SioPacket $packet): void
    {
        $this->async();
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
                $this->timers->clear('pong');
                break;
            case EioPacketType::message:
                $nsp = $packet->getNamespace();
                if ($packet->getSocketType() == SioPacketType::connect) {
                    if (!isset($this->sockets[$nsp])) {
                        $data = $packet->getData();
                        $socket = Socket::create($this, $nsp, $data ?: null);
                        if ($data) $this->auth($data);
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
                $this->upgrade($this->upgrade);
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

    public function resetTimeout(): Timer
    {
        return $this->timers->after('timeout', (self::$pingTimeout + self::$pingInterval) / 1000 + 1, fn() => $this->disconnect('Timed out'));
    }

    public function disconnect(string $reason = ''): void
    {
        foreach ($this->sockets as $connection)
            $connection->close();
        unset(self::$Connections[$this->fd]);
        $this->timers->clear();
        $server = io()->server();
        if ($server->isEstablished($this->fd))
            $server->disconnect($this->fd, reason: $reason);
        $this->status = ConnectionStatus::closed;
    }

    public function close(string $namespace): void
    {
        unset($this->sockets[$namespace]);
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
            $response->end(EioPacket::create(EioPacketType::noop));
            $this->writable = null;
        }
        $server = io()->server();
        switch ($this->transport) {
            case Transport::polling:
                if (isset($this->writable) && $this->buffer) {
                    $response = Response::create($this->writable);
                    if ($response->end(implode(chr(30), $this->buffer)))
                        $this->buffer = [];
                    $this->resetTimeout();
                    $this->writable = null;
                }
                return true;
            case Transport::websocket:
                if ($this->isConnected()) {
                    foreach ($this->buffer as $key => $data) {
                        if ($server->push($this->fd, $data))
                            unset($this->buffer[$key]);
                        else
                            return false;
                    }
                }
                return true;
            default:
                return false;
        }
    }

    public function isConnected(): bool
    {
        $io = io();
        if ($this->transport == Transport::websocket && $this->fd && $io->server()->isEstablished($this->fd))
            return true;
        return false;
    }

    public function upgrading(Transport $transport): self
    {
        $this->upgrade = $transport;
        $this->status = ConnectionStatus::upgrading;
        return $this;
    }

    /**
     * @template Auth of string|object|array|null
     * @param Auth $auth
     * @return (Auth is null? Auth: Connection)
     */
    public function auth(string|object|array $auth = null): string|object|array
    {
        if (!isset($auth)) {
            $this->async();
            return $this->auth;
        }
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

    public function upgrade(Transport $upgrade): Connection
    {
        $this->transport($upgrade);
        $this->timers->clear('timeout');
        $this->upgrade = null;
        $this->status = ConnectionStatus::upgraded;
        $this->flush();
        return $this;
    }

    public function hold(): void
    {
        $this->hold = true;
    }

    public function resume(): void
    {
        $this->hold = false;
        foreach ($this->coroutines as $coroutine)
            Coroutine::resume($coroutine);
        $this->coroutines = [];
    }

    protected function async(): void
    {
        if (!$this->hold) return;
        $this->coroutines[] = Coroutine::getCid();
        Coroutine::yield();
    }

    protected function resetPingTimeout(): Timer
    {
        return $this->timers->after('pong', Connection::$pingTimeout / 1000, fn() => $this->disconnect('ping timeout'));
    }

}