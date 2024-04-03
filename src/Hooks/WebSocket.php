<?php

namespace SwooleIO\Hooks;

use OpenSwoole\Http\Request;
use OpenSwoole\WebSocket\Frame;
use OpenSwoole\WebSocket\Server;
use SwooleIO\EngineIO\Connection;
use SwooleIO\Exceptions\InvalidPacketException;
use SwooleIO\IO;
use SwooleIO\Lib\Hook;
use SwooleIO\SocketIO\Packet;

class WebSocket extends Hook
{

    protected IO $io;

    public function __construct(object $target, bool $registerNow = false)
    {
        parent::__construct($target, $registerNow);
        $this->io = IO::instance();
    }

    /**
     * @param Server $server
     * @param Request $request
     * @return void
     */

    public function onOpen(Server $server, Request $request): void
    {
        Connection::recover($request->get['sid'])?->request($request)->fd($request->fd);
    }

    /**
     * @param Server $server
     * @param Frame $frame
     * @return void
     */
    public function onMessage(Server $server, Frame $frame): void
    {
        try {
            Connection::byFd($frame->fd)?->receive(Packet::from($frame->data));
        } catch (InvalidPacketException $e) {
        }
    }

    public function onClose(Server $server, int $fd): void
    {
        Connection::byFd($fd)?->closing();
    }
}