<?php

namespace SwooleIO\Process;

use OpenSwoole\Event;
use OpenSwoole\Timer;
use SwooleIO\IO;
use SwooleIO\Lib\Process;
use SwooleIO\Lib\SimpleEvent;
use SwooleIO\Time\TimeManager;
use function SwooleIO\io;

class Manager extends Process
{

    public function start(): void
    {
        io()->log()->info('manager started');
        Timer::tick(10000, fn() => gc_collect_cycles());
        io()->dispatch(new SimpleEvent('managerStart'));
    }

    public function exit(): void
    {
        TimeManager::end();
        Event::Exit();
        io()->dispatch(new SimpleEvent('workerStop'));
    }

    public function stop(): void
    {
        io()->dispatch(new SimpleEvent('managerStop'));
        IO::instance()->log()->info('manager stopped');
    }
}