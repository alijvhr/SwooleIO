<?php

namespace SwooleIO\Psr;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class QueueRequestHandler implements RequestHandlerInterface
{
    private array $middlewares = [];

    protected bool $isHandling = false;
    private RequestHandlerInterface $fallbackHandler;

    public function __construct(RequestHandlerInterface $fallbackHandler)
    {
        $this->fallbackHandler = $fallbackHandler;
    }

    public function add(MiddlewareInterface $middleware):self
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
        if (0 === count($stack->middlewares)) {
            return $stack->fallbackHandler->handle($request);
        }


        $middleware = array_shift($stack->middlewares);
        return $middleware->process($request, $this);
    }

}