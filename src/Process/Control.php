<?php

namespace SwooleIO\Process;

use SwooleIO\Lib\Process;

class Control extends Process
{

    public function onStart(): void
    {
        $this->io->log->info('Controller started');
        $this->io->tick(10, static fn() => gc_collect_cycles());
        $this->io->watcher->start();
        $this->io->timers->start();
    }

    public function onExit(): void
    {
        $this->io->log->info('Controller stopped');
    }
}