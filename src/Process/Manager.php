<?php

namespace SwooleIO\Process;

use OpenSwoole\Constant;
use SwooleIO\Lib\Process;

class Manager extends Process
{

    function init(): void
    {
        foreach ($this->container->listeners as $listener) {
            if (in_array($listener[2], [Constant::UNIX_STREAM, Constant::UNIX_DGRAM]))
                chmod($listener[0], 0777);
        }
        echo "manager $this->workerID started";
    }

    public function exit()
    {
        // TODO: Implement exit() method.
    }
}