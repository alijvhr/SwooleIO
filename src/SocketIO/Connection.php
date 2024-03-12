<?php

namespace SwooleIO\SocketIO;

use Psr\Http\Message\ServerRequestInterface;
use SwooleIO\EngineIO\Socket;
use SwooleIO\Lib\EventHandler;
use function SwooleIO\io;

class Connection
{

    use EventHandler;

    const reserved_events = [
        "connect",
        "connect_error",
        "disconnect",
        "disconnecting",
        "newListener",
        "removeListener",
    ];

    /** @var Connection[] */
    private static array $connections;
    protected string $cid;
    protected array $rooms = [];
    protected object $hook;

    public function __construct(protected string $sid, protected string $nsp)
    {
        $this->cid = io()->generateSid();
    }

    public static function connect(string $sid, string $namespace): Connection
    {
        return self::recover($sid, $namespace) ?? self::create($sid, $namespace);
    }

    public static function recover(string $sid, string $namespace): ?Connection
    {
        if (isset(self::$connections["$sid-$namespace"]))
            return self::$connections["$sid-$namespace"];
        $io = io();
        $session = $io->table('sid')->get($sid, 'cid')[$namespace] ?? null;
        if ($session)
            return $io->table('cid')->get($session['cid'][$namespace], 'conn');
        return null;
    }

    public static function create(string $sid, string $namespace): Connection
    {
        $connection = new Connection($sid, $namespace);
        $io = io();
        $sid = $connection->sid;
        $cid = $connection->cid;
        $nsp = $namespace;
        $io->table('sid')->push($sid, 'cid', $cid, $nsp);
        $io->table('cid')->set($cid, ['conn' => $connection]);
        $io->table('nsp')->push($nsp, 'cid', $cid);
        return self::$connections[$sid][$nsp] = $connection;
    }

    public static function bySid(string $sid, string $namespace): ?Connection
    {
        return self::recover($sid, $namespace);
    }

    public static function byPid(string $pid, string $namespace): ?Connection
    {
        $sid = io()->table('pid')->get($pid, 'sid') ?? '';
        return self::recover($sid, $namespace);
    }

    public static function byFd(int $fd, string $namespace): ?Connection
    {
        $sid = io()->table('fd')->get($fd, 'sid') ?? '';
        return self::recover($sid, $namespace);
    }

    public function save(): Connection
    {
        io()->table('cid')->set($this->cid, ['conn' => $this]);
        return $this;
    }

    public function sid(string $sid = null): string|Connection
    {
        if (!isset($sid)) return $this->sid;
        $this->sock()->sid($sid);
        $this->sid = $sid;
        return $this->sid;
    }

    public function sock(): Socket
    {
        return Socket::recover($this->sid);
    }

    public function hook(object $listener): self
    {
        $this->hook = $listener;
        return $this;
    }

    public function pid(): ?string
    {
        return $this->sid;
    }

    public function transport(): string
    {
        return $this->sock()->transport();
    }

    public function cid(): string
    {
        return $this->cid;
    }

    public function request(): ServerRequestInterface
    {
        return $this->sock()->request();
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
        if (in_array($event, self::reserved_events)) return false;
        $packet = Packet::create('event', $event, ...$data);
        return $this->sock()->push($packet);
    }

    public function close(): void
    {
//        $io = io();
//        $io->table('cid')->del($this->cid);
//        $io->table('nsp')->remove($this->nsp, 'cid', $this->cid);
//        $io->table('sid')->remove($this->sid, 'cid', $this->cid);
        unset(self::$connections[$this->fd()][$this->nsp()]);
//        return $this->sock()->disconnect();
    }

    public function fd(): int
    {
        return $this->sock()->fd();
    }

    public function nsp(): string
    {
        return $this->nsp;
    }

    public function receive(Packet $packet): void
    {
        switch ($packet->getSocketType(1)) {
            case 0:
                $this->emitReserved('connect', ['sid' => $this->cid]);
            case 2:
            case 5:
                $this->dispatch(new Event($this, $packet));

        }
    }

    public function emitReserved(string $event, mixed $data): bool
    {
        if (!in_array($event, self::reserved_events)) return false;
        $packet = Packet::create($event, $data);
        return $this->sock()->push($packet);
    }

}