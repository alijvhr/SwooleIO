<?php

namespace SwooleIO\EngineIO;

use SwooleIO\Lib\Singleton;
use SwooleIO\Psr\Event\Event;
use SwooleIO\SocketIO\Packet as SioPacket;

class MessageBroker extends Singleton
{

    function init()
    {

    }

    public function receive(Packet $packet, string $session)
    {
        $event = new Event($packet, $session);
    }

    public function send(Packet $packet, string $session)
    {

    }
}