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
        if(isset($event->type))
            $event->type = strtolower($event->type);
        return $this->dispatcher->dispatch($event);
    }

    public function on(string $event, callable $listener): static
    {
        if(!isset($this->listener)) $this->initializeEvHandler();
        $this->listener->addListener(strtolower($event), $listener);
        return $this;
    }

}