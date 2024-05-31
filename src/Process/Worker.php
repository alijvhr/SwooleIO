<?php

namespace SwooleIO\Process;

use OpenSwoole\Event;
use OpenSwoole\Timer;
use SwooleIO\EngineIO\Connection;
use SwooleIO\Lib\Process;
use SwooleIO\Lib\SimpleEvent;
use SwooleIO\Time\TimeManager;
use function SwooleIO\io;

class Worker extends Process
{

    public function start(): void
    {
//        $type = ($this->container->taskworker ? 'task-worker' : 'worker');
//        io()->log()->info("$type #$this->workerID started");
        Timer::tick(10000, fn() => gc_collect_cycles());
        io()->dispatch(new SimpleEvent('workerStart'));
    }

    public function exit(): void
    {
        TimeManager::end();
        Connection::saveAll();
        Timer::clearAll();
        Event::Exit();
        io()->dispatch(new SimpleEvent('workerStop'));
    }

    public function stop(): void
    {
        $type = ($this->container->taskworker ? 'task-worker' : 'worker');
        io()->log()->info("$type #$this->workerID stopped");
    }
}