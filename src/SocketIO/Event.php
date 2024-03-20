<?php

namespace SwooleIO\SocketIO;

class Event extends \SwooleIO\Psr\Event\Event
{

    public string $type;

    public function __construct(public Socket $socket, public ?Packet $packet = null)
    {
        $this->type = $this->packet->getEvent();
    }
}