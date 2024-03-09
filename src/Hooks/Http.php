<?php

namespace SwooleIO\Hooks;

use OpenSwoole\Core\Psr\Response as ServerResponse;
use OpenSwoole\Core\Psr\ServerRequest;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Server;
use SwooleIO\Lib\Hook;
use SwooleIO\Psr\Handler\NotFoundHandler;
use SwooleIO\Psr\Handler\QueueRequestHandler;
use SwooleIO\Psr\Handler\StackRequestHandler;
use SwooleIO\SocketIO\SocketIOMiddleware;
use function SwooleIO\io;

class Http extends Hook
{
    public StackRequestHandler $handler;

    public function __construct(Server $target, bool $registerNow = false)
    {
        parent::__construct($target, $registerNow);
        $this->handler = new QueueRequestHandler(new NotFoundHandler());
        $this->handler->add(new SocketIOMiddleware());
    }

    public function onRequest(Request $request, Response $response): void
    {
        $serverRequest = ServerRequest::from($request);
        $serverResponse = $this->handler->handle($serverRequest);
        ServerResponse::emit($response, $serverResponse);
    }

    public function onClose(Server $server, int $fd): void
    {

    }
}