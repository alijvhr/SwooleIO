<?php

namespace SwooleIO\EngineIO\Transport;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SwooleIO\EngineIO\Packet;
use SwooleIO\Lib\Transport;

class Polling extends Transport
{
    public bool $writable = false;
    protected int $closeTimeout = 30000;
    protected bool $shouldClose;

    public function __construct(protected ServerRequestInterface $request)
    {
        parent::__construct($request);
    }

    public function supportsFraming(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'polling';
    }

    public function send(Packet ...$packets): void
    {

    }

    public function onRequest(ServerRequestInterface $request, ResponseInterface $response): void
    {
        switch ($request->getMethod()) {
            case 'get':
                $this->onPollRequest($request, $response);
                break;
            case 'post':
                $this->onDataRequest($request, $response);
                break;
        }
    }

    public function onPollRequest(ServerRequestInterface $request, ResponseInterface $response)
    {

        $this->writable = true;

    }

    public function onDataRequest(ServerRequestInterface $request, ResponseInterface $response)
    {

    }
}