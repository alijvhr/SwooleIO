<?php

namespace SwooleIO\Process;

use SwooleIO\Lib\Process;

class Worker extends Process
{

    function init(): void
    {
        echo "worker $this->workerID started";
    }

    public function exit()
    {
        // TODO: Implement exit() method.
    }
}