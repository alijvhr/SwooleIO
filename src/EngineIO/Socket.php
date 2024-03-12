<?php

namespace SwooleIO\EngineIO;

use Psr\Http\Message\ServerRequestInterface;
use SwooleIO\SocketIO\Connection;
use SwooleIO\SocketIO\Packet as SioPacket;
use function SwooleIO\io;

class Socket
{

    /** @var Socket[] */
    protected static array $Sockets;
    protected ServerRequestInterface $request;

    protected int $fd = 0;
    protected string $transport = 'polling';

    public function __construct(protected string $sid)
    {
    }

    public static function bySid(string $sid): ?Socket
    {
        return self::recover($sid);
    }

    public static function recover(string $sid): ?Socket
    {
        if (isset(self::$Sockets[$sid]))
            return self::$Sockets[$sid];
        return self::$Sockets[$sid] = io()->table('sid')->get($sid, 'sock');
    }

    public static function byPid(string $pid): ?Socket
    {
        return self::recover(io()->table('pid')->get($pid, 'sid') ?? '');
    }

    public static function byFd(int $fd): ?Socket
    {
        return self::recover(io()->table('fd')->get($fd, 'sid') ?? '');
    }

    public function sid(string $sid = null): string|Socket
    {
        if (!isset($sid)) return $this->sid;
        $io = io();
        $io->table('sid')->del($this->sid);
        $this->sid = $sid;
        $io->table('sid')->set($sid, ['pid' => $this->pid()]);
        $io->table('pid')->set($this->pid(), ['sid' => $sid]);
        return $this;
    }

    public function pid(): ?string
    {
        return $this->sid;
    }

    public function request(ServerRequestInterface $request = null): ServerRequestInterface|Socket
    {
        if (!isset($request)) return $this->request;
        $this->request = $request;
        $this->save();
        return $this;
    }

    public function save(): self
    {
        io()->table('sid')->set($this->sid, ['sock' => $this]);
        return $this;
    }

    public function receive(SioPacket $packet): void
    {
        switch ($packet->getEngineType(1)) {
            case 1:
                $this->disconnect();
                break;
            case 2:
                $this->push(Packet::create('pong', $packet->getPayload()));
                break;
            case 4:
                Connection::connect($this->sid, $packet->getNamespace())->receive($packet);
                break;
            case 5:
                $this->transport('websocket');
                break;
        }
    }

    public function disconnect(): bool
    {
        self::clean($this->fd);
        return io()->server()->disconnect($this->fd);
    }

    public static function clean(int $fd): void
    {
        unset(self::$Sockets[$fd]);
    }

    public function push(Packet $packet): bool
    {
        $io = io();
        $server = $io->server();
        $payload = $packet->encode(true);
        if ($this->isConnected() && $server->push($this->fd, $payload)) return true;
        $io->table('sid')->append($this->sid, 'buffer', chr(30) . $payload);
        return false;
    }

    public function isConnected(): bool
    {
        $io = io();
        if ($this->fd() && $io->server()->isEstablished($this->fd))
            return true;
        $this->transport = 'polling';
        $io->table('fd')->del($this->fd);
        $io->table('sid')->set($this->sid, ['fd' => 0, 'transport' => $this->transport]);
        return false;
    }

    public function fd(int $fd = null): int|Socket
    {
        $io = io();
        if (!isset($fd)) return $this->transport != 'polling' ? $this->fd : $this->fd = $io->table('sid')->get($this->sid, 'fd');
        $this->fd = $fd;
        $io->table('fd')->set($this->fd, ['sid' => $this->sid]);
        $io->table('sid')->set($this->sid, ['fd' => $this->fd]);
        return $this;
    }

    public static function create(string $sid): Socket
    {
        $socket = new Socket($sid);
        $io = io();
        $pid = $socket->pid();
        $io->table('sid')->set($sid, ['fd' => 0, 'pid' => $pid, 'buffer' => '', 'cid' => [], 'transport' => $socket->transport, 'sock' => $socket]);
        $io->table('pid')->set($pid, ['sid' => $sid]);
        return self::$Sockets[$sid] = $socket;
    }

    public static function connect(string $sid): Socket
    {
        return self::recover($sid) ?? self::create($sid);
    }

    public function transport(string $transport = null): string|Socket
    {
        $io = io();
        if (!isset($transport)) return $this->transport = $io->table('sid')->get($this->sid, 'transport');
        $this->transport = $transport;
        if ($transport == 'polling') {
            $set = ['fd' => 0, 'transport' => $transport];
            $io->table('fd')->del($this->fd);
        } else $set = ['transport' => $transport];
        $io->table('sid')->set($this->sid, $set);
        return $this;
    }

    public function drain(): string
    {
        $data = ltrim(io()->table('sid')->get($this->sid, 'buffer'), chr(30));
        io()->table('sid')->set($this->sid, ['buffer' => '']);
        return $data;
    }

}