<?php

namespace SwooleIO\Process;

use OpenSwoole\Constant;
use Psr\Log\LogLevel;
use SwooleIO\IO;
use SwooleIO\Lib\Process;

class Manager extends Process
{

    public function init(): void
    {
        IO::instance()->info("manager started");
    }

    public function exit()
    {
        // TODO: Implement exit() method.
    }
}