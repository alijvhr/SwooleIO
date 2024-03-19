<?php

namespace SwooleIO\EngineIO;

use Psr\Http\Message\ServerRequestInterface;
use SwooleIO\Constants\SocketStatus;
use SwooleIO\Constants\Transport;
use SwooleIO\SocketIO\Connection;
use SwooleIO\SocketIO\Packet as SioPacket;
use function SwooleIO\io;

class Socket
{

    /** @var Socket[] */
    protected static array $Sockets = [];
    protected ServerRequestInterface $request;

    protected int $fd = 0;
    protected Transport $transport = Transport::polling;
    protected string $buffer = '';

    /** @var Connection[] */
    protected array $connections = [];
    protected string $pid;
    protected SocketStatus $status = SocketStatus::disconnected;
    protected ?Transport $upgrade;

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
        if (isset($transport)) $socket->transport($transport)->save();
        return $socket;
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

    public function is(SocketStatus $status): bool
    {
        return $status == $this->status;
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
        $io->log()->info("on worker " . $server->getWorkerId());
        switch ($packet->getEngineType(1)) {
            case 1:
                $this->status = SocketStatus::closing;
                $this->disconnect();
                $this->status = SocketStatus::closed;
                break;
            case 2:
                $payload = $packet->getPayload();
                $packet = Packet::create('pong', $payload);
                if ($this->status == SocketStatus::connected && $payload == 'probe') {
                    $this->upgrading(Transport::websocket);
                    if ($this->upgrade == Transport::websocket)
                        $server->push($this->fd, $packet->encode());
                } else
                    $this->push($packet);
                break;
            case 4:
                $nsp = $packet->getNamespace();
                ($this->connections[$nsp] ?? Connection::create($this, $nsp))->receive($packet);
                break;
            case 5:
                $this->transport($this->upgrade);
                $this->upgrade = null;
                $this->status = SocketStatus::upgraded;
                break;
        }
    }

    public function disconnect(): bool
    {
        foreach ($this->connections as $connection)
            $connection->close();
        unset(self::$Sockets[$this->fd]);
        return io()->server()->disconnect($this->fd);
    }

    public function close(string $namespace): void
    {
        unset($this->connections[$namespace]);
    }

    public function upgrading(Transport $transport): self
    {
        $this->upgrade = $transport;
        $this->status = SocketStatus::upgrading;
        return $this;
    }

    public function push(Packet $packet): bool
    {
        $server = io()->server();
        $payload = $packet->encode(true);
        if ($this->isConnected() && $server->push($this->fd, $payload)) return true;
        if ($this->buffer) $this->buffer .= chr(30);
        $this->buffer .= $payload;
        return false;
    }

    public function isConnected(): bool
    {
        $io = io();
        if ($this->status == SocketStatus::upgraded && $this->fd && $io->server()->isEstablished($this->fd))
            return true;
        return false;
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

    public function drain(): string
    {
        $data = $this->buffer;
        $this->buffer = '';
        return $data;
    }

}