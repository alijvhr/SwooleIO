<?php

namespace SwooleIO\Hooks;

use Sparrow\Lib\Service\Packet\Call;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use SwooleIO\EngineIO\Connection;
use SwooleIO\Exceptions\InvalidPacketException;
use SwooleIO\IO;
use SwooleIO\Lib\Hook;
use SwooleIO\SocketIO\Packet;

class UDP extends Hook
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

    public function onPacket(Server $server, string $data, array $client): void
    {
        $packet = @unserialize($data);
        if ($packet instanceof Call) {
            $data = $packet->to->{$packet->method}(...$packet->data);
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