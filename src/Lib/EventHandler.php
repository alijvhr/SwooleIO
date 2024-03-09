<?php

namespace SwooleIO\Lib;

use Psr\EventDispatcher\StoppableEventInterface;
use SwooleIO\Psr\Event\EventDispatcher;
use SwooleIO\Psr\Event\ListenerProvider;

trait EventHandler
{

    protected ListenerProvider $listener;
    protected EventDispatcher $dispatcher;

    public function __construct()
    {
        $this->listener = new ListenerProvider();
        $this->dispatcher = new EventDispatcher($this->listener);
    }

    public function dispatch(StoppableEventInterface $event): StoppableEventInterface
    {
        return $this->dispatcher->dispatch($event);
    }

    public function on(string $event, callable $listener): ListenerProvider
    {
        return $this->listener->addListener($event, $listener);
    }

}