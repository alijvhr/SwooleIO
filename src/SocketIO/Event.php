<?php

namespace SwooleIO\SocketIO;

class Event extends \SwooleIO\Psr\Event\Event
{

    public string $event;
    public function __construct(public Connection $connection, public Packet $packet)
    {
        $this->event = $this->packet->getEvent();
    }
}