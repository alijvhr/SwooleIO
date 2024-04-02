<?php

namespace SwooleIO\Process;

use OpenSwoole\Timer;
use SwooleIO\EngineIO\Connection;
use SwooleIO\Lib\Process;
use SwooleIO\Lib\SimpleEvent;
use function SwooleIO\io;

class Worker extends Process
{

    public function init(): void
    {
        $type = ($this->container->taskworker ? "task-worker" : "worker");
        io()->log()->info("$type #$this->workerID started");
        Timer::tick(10000, fn() => gc_collect_cycles());
        io()->dispatch(new SimpleEvent('workerStart'));
    }

    public function exit(): void
    {
        Connection::saveAll();
        Timer::clearAll();
        $type = ($this->container->taskworker ? "task-worker" : "worker");
        io()->log()->info("$type #$this->workerID stopped");
        io()->dispatch(new SimpleEvent('workerStop'));
    }
}