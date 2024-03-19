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
    protected string $cid;
    protected array $rooms = [];
    protected object $hook;

    public function __construct(protected Socket $socket, protected string $nsp)
    {
        $this->cid = io()->generateSid();
        io()->table('cid')->set($this->cid(), ['sid' => $this->socket->sid()]);
    }

    public static function connect(Socket $socket, string $namespace): Connection
    {
        return self::create($socket, $namespace);
    }

    public static function create(Socket $socket, string $namespace): Connection
    {
        return new Connection($socket, $namespace);
    }

    public function hook(object $listener): self
    {
        $this->hook = $listener;
        return $this;
    }

    public function cid(): string
    {
        return $this->cid;
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
        return $this->socket->push($packet);
    }

    public function close(): void
    {
        $this->socket->close($this->nsp);
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
        return $this->socket->push($packet);
    }

    public function socket(): Socket
    {
        return $this->socket;
    }

}