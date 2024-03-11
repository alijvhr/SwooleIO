<?php

namespace SwooleIO\Hooks;

use OpenSwoole\Core\Psr\ServerRequest;
use OpenSwoole\Http\Request;
use OpenSwoole\WebSocket\Frame;
use OpenSwoole\WebSocket\Server;
use SwooleIO\EngineIO\InvalidPacketException;
use SwooleIO\EngineIO\Packet as EioPacket;
use SwooleIO\IO;
use SwooleIO\Lib\Hook;
use SwooleIO\SocketIO\Packet;
use SwooleIO\SocketIO\Connection;

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
        /** @var ServerRequest $serverRequest */
        $serverRequest = ServerRequest::from($request);
        $sid = $serverRequest->getQueryParam('sid', '');
        Connection::bySid($sid, $serverRequest)->fd($request->fd);
    }

    /**
     * @param Server $server
     * @param Frame $frame
     * @return void
     * @throws InvalidPacketException
     */
    public function onMessage(Server $server, Frame $frame): void
    {
        $packet = Packet::from($frame->data);
        $io = $this->io;
        $socket = Connection::byFd($frame->fd);
        switch ($packet->getEngineType(true)) {
            case 2:
                $socket->emit(EioPacket::create('pong', $packet->getPayload()));
                break;
            case 4:
                $io->receive($socket, $packet);
        }
    }

    public function onClose(Server $server, int $fd): void
    {
        Connection::clean($fd);
    }
}