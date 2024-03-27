<?php

namespace SwooleIO\SocketIO;

use SwooleIO\Constants\ReservedEvents;
use SwooleIO\Constants\SioPacketType;
use SwooleIO\EngineIO\Connection;
use SwooleIO\Lib\EventHandler;
use function SwooleIO\io;

class Socket
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

    public function __construct(protected Connection $connection, protected string $nsp)
    {
        $this->cid = io()->generateSid();
        io()->table('cid')->set($this->cid(), ['sid' => $this->connection->sid()]);
    }

    public function cid(): string
    {
        return $this->cid;
    }

    public static function connect(Connection $connection, string $namespace): Socket
    {
        return self::create($connection, $namespace);
    }

    public static function create(Connection $connection, string $namespace): Socket
    {
        return new Socket($connection, $namespace);
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
        $packet = Packet::create(SioPacketType::event, $event, ...$data);
        return $this->connection->push($packet);
    }

    public function close(): void
    {
        $this->connection->close($this->nsp);
    }

    public function nsp(): string
    {
        return $this->nsp;
    }

    public function receive(Packet $packet): void
    {
        $ev = new Event($this, $packet);
        switch ($packet->getSocketType(1)) {
            case SioPacketType::event:
            case SioPacketType::binary_event:
                $this->dispatch($ev);
                break;

        }
    }

    public function emitReserved(SioPacketType $type, mixed $data): bool
    {
        $packet = Packet::create($type, $data);
        return $this->connection->push($packet);
    }

    public function socket(): Connection
    {
        return $this->connection;
    }

}