<?php

namespace SwooleIO\Lib;

use Psr\EventDispatcher\StoppableEventInterface;
use SwooleIO\Psr\Event\EventDispatcher;
use SwooleIO\Psr\Event\ListenerProvider;

trait EventHandler
{

    protected ListenerProvider $eventListener;
    protected EventDispatcher $eventDispatcher;

    protected function initializeEvHandler(): void
    {
        $this->eventListener = new ListenerProvider();
        $this->eventDispatcher = new EventDispatcher($this->eventListener);
    }

    public function dispatch(StoppableEventInterface $event): StoppableEventInterface
    {
        if(!isset($this->eventListener)) $this->initializeEvHandler();
        if(isset($event->type))
            $event->type = strtolower($event->type);
        return $this->eventDispatcher->dispatch($event);
    }

    public function on(string $event, callable $listener): static
    {
        if(!isset($this->eventListener)) $this->initializeEvHandler();
        $this->eventListener->addListener(strtolower($event), $listener);
        return $this;
    }

}