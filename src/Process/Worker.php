<?php

namespace SwooleIO\Process;

use SwooleIO\IO;
use SwooleIO\Lib\Process;

class Worker extends Process
{

    public function init(): void
    {

        IO::instance()->info(($this->container->taskworker?"task-worker":"worker")." $this->workerID started");
    }

    public function exit()
    {
        // TODO: Implement exit() method.
    }
}