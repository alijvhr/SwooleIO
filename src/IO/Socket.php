<?php

namespace SwooleIO\IO;

use OpenSwoole\WebSocket\Server;
use Psr\Http\Message\ServerRequestInterface;
use SwooleIO\EngineIO\Packet;
use SwooleIO\IO;
use SwooleIO\Memory\WrongTypeColumn;
use function SwooleIO\io;

class Socket
{

    protected string $sid;
    protected int $fd = 0;
    protected IO $io;
    protected Server $server;

    public function __construct(public ServerRequestInterface $request, protected string $pid)
    {
        $this->io = io();
        $this->server = $this->io->server();
        if (isset($this->request->fd))
            $this->fd = $this->request->fd;
    }

    public static function bySid(string $sid, ServerRequestInterface $request = null): ?Socket
    {
        $io = io();
        $pid = $io->table('sid')->get($sid, 'pid');
        return $io->recover($pid, $request);
    }

    public static function byPid(string $pid, ServerRequestInterface $request = null): ?Socket
    {
        return io()->recover($pid, $request);
    }

    public static function byFd(int $fd, ServerRequestInterface $request = null): ?Socket
    {
        $io = io();
        $pid = $io->table('fd')->get($fd, 'pid');
        return $io->recover($pid, $request);
    }

    public function flush(): ?string
    {
        return ltrim(io()->table('pid')->get($this->pid, 'buffer'), chr(30));
    }

    public function fd(int $fd = null): int
    {
        if (isset($fd)) {
            $this->fd = $fd;
            $this->io->table('fd')->set($this->fd, ['pid' => $this->pid]);
            $this->io->table('pid')->set($this->pid, ['fd' => $this->fd]);
        }
        return $this->fd;
    }

    public function sid(string $sid = null): ?string
    {
        if (isset($sid)) {
            $this->sid = $sid;
            $this->io->table('sid')->set($this->sid, ['pid' => $this->pid]);
            $this->io->table('pid')->set($this->pid, ['sid' => $this->sid]);
        }
        return $this->sid ?? null;
    }

    public function pid(): ?string
    {
        return $this->pid;
    }

    public function emit(Packet $packet): bool
    {
        $payload = $packet->encode(true);
        if (isset($this->fd) && $this->server->isEstablished($this->fd))
            if (!$this->server->push($this->fd, $payload))
                $this->io->table('pid')->push($this->pid, 'buffer', chr(30) . $payload);
        return false;
    }

    public function disconnect(): bool
    {
        return $this->server->disconnect($this->fd);
    }

}