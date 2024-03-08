<?php

namespace SwooleIO\Psr\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface StackRequestHandler extends RequestHandlerInterface
{

    public function add(MiddlewareInterface $middleware): StackRequestHandler;

    public function handle(ServerRequestInterface $request): ResponseInterface;

}