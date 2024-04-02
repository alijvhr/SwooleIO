<?php

namespace SwooleIO\Process;

use OpenSwoole\Timer;
use SwooleIO\IO;
use SwooleIO\Lib\Process;
use SwooleIO\Lib\SimpleEvent;
use function SwooleIO\io;

class Manager extends Process
{

    public function init(): void
    {
        IO::instance()->log()->info("manager started");
        Timer::tick(10000, fn() => gc_collect_cycles());
        io()->dispatch(new SimpleEvent('managerStart'));
    }

    public function exit(): void
    {
        io()->dispatch(new SimpleEvent('managerStop'));
        IO::instance()->log()->info("manager stopped");
        Timer::clearAll();
    }
}