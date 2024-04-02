<?php

namespace SwooleIO\SocketIO;

use SwooleIO\Constants\SioPacketType;
use SwooleIO\Constants\Transport;
use SwooleIO\EngineIO\Connection;
use SwooleIO\Lib\EventHandler;
use function SwooleIO\io;

class Socket implements SocketInterface
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
    protected string $cid;
    protected array $rooms = [];
    protected object $hook;

    public function __construct(protected Connection $connection, protected string $nsp, mixed $auth = null)
    {
        if (isset($auth))
            $this->connection->auth($auth);
        $this->cid = io()->generateSid();
        io()->table('cid')->set($this->cid(), ['sid' => $this->connection->sid()]);
    }

    public function auth(): array|object|string
    {
        return $this->connection->auth();
    }

    public function cid(): string
    {
        return $this->cid;
    }

    public function sid(): string
    {
        return $this->connection->sid();
    }

    public static function connect(Connection $connection, string $namespace): Socket
    {
        return self::create($connection, $namespace);
    }

    public static function create(Connection $connection, string $namespace, mixed $auth = null): Socket
    {
        return new Socket($connection, $namespace, $auth);
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    public function hook(object $listener): self
    {
        $this->hook = $listener;
        return $this;
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
        $packet = Packet::create(SioPacketType::event, $event, ...$data)->setNamespace($this->nsp);
        return $this->connection->push($packet);
    }

    public function close(): void
    {
        $this->dispatch(Event::create('disconnect', $this));
        $this->connection->close($this->nsp);
    }

    public function nsp(): string
    {
        return $this->nsp;
    }

    public function receive(Packet $packet): void
    {
        $ev = new Event($this, $packet);
        switch ($packet->getSocketType()) {
            case SioPacketType::event:
            case SioPacketType::binary_event:
                $this->dispatch($ev);
                break;

        }
    }

    public function emitReserved(SioPacketType $type, mixed $data): bool
    {
        $packet = Packet::create($type, $data)->setNamespace($this->nsp);
        return $this->connection->push($packet);
    }

    public function transport(): Transport
    {
        return $this->connection->transport();
    }

    public function workerId(): int
    {
        return io()->server()->getWorkerId();
    }

    public function fd(): ?int
    {
        return $this->connection->fd();
    }
}