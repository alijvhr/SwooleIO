<?php

namespace SwooleIO\Process;

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
    }

    public function exit(): void
    {
        Connection::saveAll();
    }
}