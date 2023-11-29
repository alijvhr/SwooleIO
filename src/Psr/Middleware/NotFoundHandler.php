<?php

namespace SwooleIO\Psr\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use OpenSwoole\Core\Psr\Response;

class NotFoundHandler implements RequestHandlerInterface {
    private ResponseInterface $responsePrototype;

    public function __construct()
    {
        $this->responsePrototype = new Response('Not Found', 404, 'NotFound');
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        echo "not found\n";
        return $this->responsePrototype;
    }
};
