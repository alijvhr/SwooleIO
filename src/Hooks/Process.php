<?php

namespace SwooleIO\Hooks;

use Swoole\Event as SwEvent;
use SwooleIO\IO;
use SwooleIO\Lib\Hook;
use SwooleIO\Lib\ProcessID;
use SwooleIO\Time\TimeManager;

abstract class Process extends Hook
{


    protected ProcessID $pid;
    protected readonly IO $io;

    public function __construct(object $target, bool $registerNow = false)
    {
        $this->io = IO::instance();
        $this->pid = \SwooleIO\Lib\Process::ID();
        parent::__construct($target, $registerNow);
    }

    protected function log(string $event): void
    {
        $this->io->log->info("$this->pid $event");
    }

    protected function exit(): void
    {
        TimeManager::end();
        SwEvent::Exit();
    }
}