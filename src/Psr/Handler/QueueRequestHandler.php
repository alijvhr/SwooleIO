<?php

namespace SwooleIO\Psr\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class QueueRequestHandler implements StackRequestHandler
{
    protected array $middlewares = [];

    protected bool $isHandling = false;
    private RequestHandlerInterface $fallbackHandler;

    public function __construct(RequestHandlerInterface $fallbackHandler)
    {
        $this->fallbackHandler = $fallbackHandler;
    }

    public function add(MiddlewareInterface $middleware): StackRequestHandler
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->isHandling) {
            $stack = clone $this;
            $stack->isHandling = true;
        } else
            $stack = $this;
        return array_pop($stack->middlewares)?->process($request, $stack) ?? $stack->fallbackHandler->handle($request);
    }

}