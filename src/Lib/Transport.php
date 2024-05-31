<?php

namespace SwooleIO\Lib;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SwooleIO\EngineIO\Packet;
use SwooleIO\Psr\Event\Event;

abstract class Transport
{

    use  EventHandler;

    public string $sid;
    public bool $writable = false;
    public int $protocol;

    protected string $readyState = 'open';
    protected bool $discarded = false;
    protected bool $binarySupport;

    public function __construct(protected ServerRequestInterface $request)
    {
        $this->protocol = $request->getQueryParam('EIO', 4);
        $this->binarySupport = $request->getQueryParam('b64') || $this->request->getMethod() == 'post';
    }

    public function readyState(string $state = null): string|self
    {
        if (!isset($state)) return $this->readyState;
        else $this->readyState = $state;
        return $this;
    }

    public function discard(): void
    {
        $this->discarded = true;
    }

    public function close(callable $fn = null): void
    {
        if ('closed' === $this->readyState || 'closing' === $this->readyState) return;

        $this->readyState = 'closing';
        $this->onClose();
    }

    public function onClose(): void
    {
        $this->readyState = 'closed';
        $this->dispatch(Event::with(['transport' => $this]));
    }

    abstract public function supportsFraming(): bool;

    abstract public function name(): string;

    abstract public function send(Packet ...$packets): void;

    public function onRequest(ServerRequestInterface $request, ResponseInterface $response): void
    {
        $this->request = $request;
    }

    public function onData(string $data): void
    {
        $this->onPacket(Packet::from($data));
    }

    public function onPacket(Packet $packet): void
    {
        $this->dispatch(Event::with(['transport' => $this, 'packet' => $packet]));
    }
}
