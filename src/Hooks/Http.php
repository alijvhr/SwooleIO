<?php

namespace SwooleIO\Hooks;

use OpenSwoole\Core\Psr\Response as ServerResponse;
use OpenSwoole\Core\Psr\ServerRequest;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use SwooleIO\Lib\Hook;
use SwooleIO\Psr\QueueRequestHandler;

class Http extends Hook
{
    protected QueueRequestHandler $handler;

    public function __construct(object $target, bool $registerNow = false)
    {
        parent::__construct($target, $registerNow);
    }

    public function setHandler(QueueRequestHandler $handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    public function onRequest(Request $request, Response $response): void
    {
        $serverRequest = ServerRequest::from($request);
        $serverResponse = $this->handler->handle($serverRequest);
        ServerResponse::emit($response, $serverResponse);
    }
}