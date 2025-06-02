<?php

namespace SwooleIO\Hooks;

use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use SwooleIO\Constants\EioPacketType;
use SwooleIO\Constants\Transport;
use SwooleIO\EngineIO\Connection;
use SwooleIO\EngineIO\Packet as EioPacket;
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
        if (isset($request->get['sid']))
            $connection = Connection::recover($request->get['sid'])->request($request)->fd($request->fd);
        if (!isset($connection)) {
            $connection = Connection::create($sid = $this->io->generateSid(), Transport::websocket)->request($request)->fd($request->fd)->save(true);
            $connection->push(EioPacket::create(EioPacketType::open, ['sid' => $sid, 'upgrades' => [], 'maxPayload' => 1000000, 'pingInterval' => Connection::$pingInterval, 'pingTimeout' => Connection::$pingTimeout]));
        }
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