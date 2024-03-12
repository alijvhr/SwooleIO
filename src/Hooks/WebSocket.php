<?php

namespace SwooleIO\Hooks;

use OpenSwoole\Core\Psr\ServerRequest;
use OpenSwoole\Http\Request;
use OpenSwoole\WebSocket\Frame;
use OpenSwoole\WebSocket\Server;
use SwooleIO\EngineIO\Socket;
use SwooleIO\IO;
use SwooleIO\Lib\Hook;
use SwooleIO\SocketIO\Packet;
use function SwooleIO\io;

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
        $sock = Socket::bySid($request->get['sid']);
        if($sock)
            io()->log()->info('hello...');
        $sock?->request(ServerRequest::from($request))->fd($request->fd);
    }

    /**
     * @param Server $server
     * @param Frame $frame
     * @return void
     */
    public function onMessage(Server $server, Frame $frame): void
    {
        Socket::byFd($frame->fd)?->receive(Packet::from($frame->data));
    }

    public function onClose(Server $server, int $fd): void
    {
        $sock = Socket::byFd($fd);
        if ($sock?->transport() == 'websocket') {
            $sock->transport('polling');
            io()->log()->info('bye!');
        }
//        io()->log()->info("closed on req $fd");
//        Socket::clean($fd);
    }
}