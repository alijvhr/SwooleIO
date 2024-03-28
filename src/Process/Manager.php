<?php

namespace SwooleIO\Process;

use OpenSwoole\Constant;
use OpenSwoole\Timer;
use Psr\Log\LogLevel;
use SwooleIO\IO;
use SwooleIO\Lib\Process;

class Manager extends Process
{

    public function init(): void
    {
        IO::instance()->log()->info("manager started");
        Timer::tick(10000, fn() => gc_collect_cycles());
    }

    public function exit()
    {
        // TODO: Implement exit() method.
    }
}