<?php

namespace SwooleIO\Psr\Handler;

use OpenSwoole\Core\Psr\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class NotFoundHandler implements RequestHandlerInterface {
    private ResponseInterface $responsePrototype;

    public function __construct()
    {
        $this->responsePrototype = new Response('Not Found', 404, 'NotFound');
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responsePrototype;
    }
};
