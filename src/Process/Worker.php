<?php

namespace SwooleIO\Process;

use OpenSwoole\Timer;
use SwooleIO\EngineIO\Connection;
use SwooleIO\IO;
use SwooleIO\Lib\Process;

class Worker extends Process
{

    public function init(): void
    {
        $io = IO::instance();
        $type = ($this->container->taskworker ? "task-worker" : "worker");
        $io->log()->info("$type #$this->workerID started");
        Timer::tick(10000, fn() => gc_collect_cycles());
    }

    public function exit(): void
    {
        Connection::saveAll();
    }
}