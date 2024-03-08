<?php

namespace SwooleIO\EngineIO;

use OpenSwoole\Server\Task;
use SwooleIO\Lib\Singleton;
use SwooleIO\Psr\Event\Event;
use SwooleIO\Psr\Event\EventDispatcher;
use SwooleIO\SocketIO\Packet as SioPacket;
use SwooleIO\SocketIO\Nsp;

class MessageBroker extends Singleton
{

    function init()
    {

    }

    public function receive(string $payload, string $session): void
    {
        $packets = new SioPacket($payload);
        /**
         * @var SioPacket $packet
         */
        foreach ($packets as $packet)
            Nsp::get($packet->getNamespace())->receive($session, $packet->getEvent(), $packet->getParams(), $packet->getId());
    }

    public function send(Packet $packet, string $session)
    {

    }

    public function flush(string $sid): string
    {

        return '';
    }
}