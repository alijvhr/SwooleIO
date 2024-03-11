<?php

namespace SwooleIO\EngineIO;

use Psr\Http\Message\ServerRequestInterface;
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

    public static function connect(string $sid): Socket
    {
        return self::recover($sid) ?? self::store(new Socket($sid));
    }

    public static function recover(string $sid): ?Socket
    {
        if (isset(self::$Sockets[$sid]))
            return self::$Sockets[$sid];
        return self::$Sockets[$sid] = io()->table('sid')->get($sid, 'conn');
    }

    public static function store(Socket $socket): Socket
    {
        $io = io();
        $sid = $socket->sid();
        $fd = $socket->fd();
        $pid = $socket->pid();
        $io->table('sid')->set($sid, ['fd' => $fd, 'pid' => $pid, 'buffer' => '', 'cid' => [], 'transport' => $socket->transport, 'sock' => $socket]);
        $io->table('pid')->set($pid, ['sid' => $sid]);
        $io->table('fd')->set($fd, ['sid' => $sid]);
        return self::$Sockets[$sid] = $socket;
    }

    public function sid(string $sid = null): string|Socket
    {
        if (!isset($sid)) return $this;
        $io = io();
        $io->table('sid')->del($this->sid);
        $this->sid = $sid;
        $io->table('sid')->set($sid, ['pid' => $this->pid()]);
        $io->table('pid')->set($this->pid(), ['sid' => $sid]);
        return $sid;
    }

    public function pid(): ?string
    {
        return $this->sid;
    }

    public function fd(int $fd = null): int|Socket
    {
        if (!isset($fd)) return $this;
        $io = io();
        $this->fd = $fd;
        $this->transport = 'websocket';
        $io->table('fd')->set($this->fd, ['pid' => $this->sid]);
        $io->table('sid')->set($this->sid, ['fd' => $this->fd, 'transport' => $this->transport]);
        return $fd;
    }

    public static function bySid(string $sid): ?Socket
    {
        return self::recover($sid);
    }

    public static function byPid(string $pid): ?Socket
    {
        return self::recover(io()->table('pid')->get($pid, 'sid'));
    }

    public static function byFd(int $fd): ?Socket
    {
        return self::recover(io()->table('fd')->get($fd, 'sid'));
    }

    public function push(Packet $packet): bool
    {
        $io = io();
        $server = $io->server();
        $payload = $packet->encode(true);
        if (isset($this->fd) && $server->isEstablished($this->fd))
            if (!$server->push($this->fd, $payload)) {
                $io->table('sid')->push($this->sid, 'buffer', chr(30) . $payload);
                return false;
            }
        return true;
    }

    public function transport(int $transport = null): string|Socket
    {
        if (!isset($transport)) return $this;
        $this->transport = $transport;
        io()->table('sid')->set($this->sid, ['transport' => $transport]);

        return $this->fd;
    }

    public function request(ServerRequestInterface $request = null): ServerRequestInterface|Socket
    {
        //TODO: cache request by number
        if (!isset($request)) return $this;
        return $this->request = $request;
    }

    public function drain(): ?string
    {
        return ltrim(io()->table('sid')->get($this->sid, 'buffer'), chr(30));
    }

    public function isConnected(): bool
    {
        $io = io();
        if (isset($this->fd) && $io->server()->isEstablished($this->fd))
            return true;
        $this->transport = 'polling';
        $io->table('fd')->del($this->fd);
        $io->table('sid')->set($this->sid, ['fd' => 0, 'transport' => $this->transport]);
        return false;
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

}