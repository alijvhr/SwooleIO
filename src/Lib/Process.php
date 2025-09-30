<?php

namespace SwooleIO\Lib;

use Swoole\Event as SwEvent;
use Swoole\Process as SwProcess;
use SwooleIO\IO;
use SwooleIO\Time\TimeManager;
use SwooleIO\Time\Timer;

abstract class Process extends SwProcess
{

    /** @var static $current */
    protected static Process $current;
    protected IO $io;

    public protected(set) bool $started = false;

    public static function id(): ProcessID
    {
        return IO::instance()->id();
    }

    public function __construct()
    {
        $this->io = IO::instance();
        parent::__construct($this->run(...), enable_coroutine: true);
    }

    public function start(): bool|int
    {
        $this->started = true;
        return parent::start();
    }

    public function stop(): void
    {
        if (isset(static::$current)) {
            $this->onStop();
        }
        $this->exit();
    }

    public function exit(int $exit_code = 0): void
    {
        $this->started = false;
        if (isset(static::$current)) {
            $this->onExit();
            TimeManager::end();
            SwEvent::exit();
        } else {
            parent::exit($exit_code);
        }
    }

    protected function run(): void
    {
        $this->started = true;
        static::signal(SIGTERM, fn() => $this->stop());
        Timer::tick(10, static fn() => gc_collect_cycles());
        static::$current = $this;
        $this->onStart();
    }

    abstract public function onStart();

    public function onStop(): void { }

    public function onExit(): void { }

}