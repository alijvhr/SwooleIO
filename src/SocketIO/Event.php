<?php

namespace SwooleIO\SocketIO;

class Event extends \SwooleIO\Psr\Event\Event
{

    public string $type;

    public function __construct(public Socket $socket, public ?Packet $packet = null)
    {
        if (isset($packet))
            $this->type = $this->packet?->getEvent();
    }

    public static function create(string $type, Socket $socket, ?Packet $packet = null): static
    {
        $event = new static($socket, $packet);
        $event->type = $type;
        return $event;
    }
}