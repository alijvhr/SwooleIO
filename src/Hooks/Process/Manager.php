<?php

namespace SwooleIO\Hooks\Process;

use SwooleIO\Hooks\Process;
use SwooleIO\VO\Event;

class Manager extends Process
{

    public function onManagerStart(): void
    {
        $this->io->id(service: 'manager', worker: '');
        $this->log('Started');
        $this->io->dispatch(new Event('managerStart'));
    }

//    public function onManagerExit(): void
//    {
//        $this->io->log->info('Manager Exit');
//        $this->exit();
//    }

    public function onManagerStop(): void
    {
        $this->io->dispatch(new Event('managerStop'));
        $this->log('Stopped');
    }
}