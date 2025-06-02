<?php

namespace SwooleIO\Process;

use Swoole\Event;
use SwooleIO\IO;
use SwooleIO\Lib\Process;
use SwooleIO\Lib\SimpleEvent;
use SwooleIO\Time\TimeManager;
use function SwooleIO\io;

class Manager extends Process
{

    public function start(): void
    {
        io()->log->info('manager started');
//        io()->tick(10, fn() => gc_collect_cycles());
        io()->dispatch(new SimpleEvent('managerStart'));
    }

    public function exit(): void
    {
        IO::instance()->log->info('manager exit');
        TimeManager::end();
        Event::Exit();
    }

    public function stop(): void
    {
        io()->dispatch(new SimpleEvent('managerStop'));
        IO::instance()->log->info('manager stopped');
    }
}