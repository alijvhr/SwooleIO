<?php

namespace SwooleIO\Lib;

use Psr\EventDispatcher\StoppableEventInterface;
use SwooleIO\Psr\Event\EventDispatcher;
use SwooleIO\Psr\Event\ListenerProvider;

trait EventHandler
{

    protected ListenerProvider $listener;
    protected EventDispatcher $dispatcher;

    protected function initializeEvHandler(): void
    {
        $this->listener = new ListenerProvider();
        $this->dispatcher = new EventDispatcher($this->listener);
    }

    public function dispatch(StoppableEventInterface $event): StoppableEventInterface
    {
        if(!isset($this->listener)) $this->initializeEvHandler();
        return $this->dispatcher->dispatch($event);
    }

    public function on(string $event, callable $listener): ListenerProvider
    {
        if(!isset($this->listener)) $this->initializeEvHandler();
        return $this->listener->addListener($event, $listener);
    }

}