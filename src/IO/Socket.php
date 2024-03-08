<?php

namespace SwooleIO\IO;

use OpenSwoole\WebSocket\Server;
use Psr\Http\Message\ServerRequestInterface;
use SwooleIO\IO;
use SwooleIO\SocketIO\Packet;
use function SwooleIO\io;

class Socket
{


    /** @var Socket[] */
    protected static array $sockets;
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

    public static function fetch(string $pid, ServerRequestInterface $request = null): Socket
    {
        return self::recover($pid, $request) ?? self::add(new Socket($request, $pid));
    }

    public static function recover(string $pid, ServerRequestInterface $request = null): ?Socket
    {
        $socket = null;
        if (isset(self::$sockets[$pid]))
            $socket = self::$sockets[$pid];
        else {
            $session = io()->table('pid')->get($pid);
            if (isset($session)) {
                $socket = new Socket($request, $pid);
                $socket->sid($session['sid']);
                $socket->fd($session['fd']);
            }
        }
        if (isset($socket, $request)) $socket->request = $request;
        return $socket;
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

    public function fd(int $fd = null): int
    {
        if (isset($fd)) {
            $this->fd = $fd;
            $this->io->table('fd')->set($this->fd, ['pid' => $this->pid]);
            $this->io->table('pid')->set($this->pid, ['fd' => $this->fd]);
        }
        return $this->fd;
    }

    public static function add(Socket $socket): Socket
    {
        $io = io();
        $io->table('pid')->set($socket->pid(), ['fd' => $socket->fd(), 'sid' => $socket->sid(), 'room' => [], 'buffer' => '']);
        if ($socket->sid())
            $io->table('sid')->set($socket->sid(), ['pid' => $socket->pid()]);
        if ($socket->fd())
            if ($socket->fd())
                $io->table('fd')->set($socket->fd(), ['pid' => $socket->pid()]);
        return self::$sockets[$socket->pid()] = $socket;
    }

    public function pid(): ?string
    {
        return $this->pid;
    }

    public static function bySid(string $sid, ServerRequestInterface $request = null): ?Socket
    {
        $pid = io()->table('sid')->get($sid, 'pid');
        return self::recover($pid, $request);
    }

    public static function byPid(string $pid, ServerRequestInterface $request = null): ?Socket
    {
        return self::recover($pid, $request);
    }

    public static function byFd(int $fd, ServerRequestInterface $request = null): ?Socket
    {
        $pid = io()->table('fd')->get($fd, 'pid');
        return self::recover($pid, $request);
    }

    public function flush(): ?string
    {
        return ltrim(io()->table('pid')->get($this->pid, 'buffer'), chr(30));
    }

    public function isConnected(): bool
    {
        if (isset($this->fd) && $this->server->isEstablished($this->fd))
            return true;
        $io = io();
        $io->table('fd')->del($this->fd);
        $io->table('pid')->set($this->pid, ['fd' => 0]);
        return false;
    }

    public function write(mixed ...$data): bool
    {
        return $this->send(...$data);
    }

    public function send(mixed ...$data): bool
    {
        return $this->emit('message', ...$data);
    }

    public function emit(string $event, mixed ...$data): bool
    {
        if()
        $packet = Packet::create('event', $event, ...$data);
        return $this->push($packet);
    }

    public function push(Packet $packet): bool
    {
        $payload = $packet->encode(true);
        if (isset($this->fd) && $this->server->isEstablished($this->fd))
            if (!$this->server->push($this->fd, $payload)) {
                $this->io->table('pid')->push($this->pid, 'buffer', chr(30) . $payload);
                return false;
            }
        return true;
    }

    public function disconnect(): bool
    {
        self::clean($this->fd);
        return $this->server->disconnect($this->fd);
    }

    public static function clean(int $fd): void
    {
        unset(self::$sockets[$fd]);
    }

}