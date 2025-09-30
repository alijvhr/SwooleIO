<?php

namespace SwooleIO\IO;

use SwooleIO\Time\Timer;

trait Timers
{

    public function defer(callable $fn): void
    {
        if ($this->started) {
            swoole_event_defer($fn);
        } else {
            $this->on('start', fn() => swoole_event_defer($fn));
        }
    }

    public function tick(float $interval, callable $fn, array $arguments = []): Timer
    {
        return $this->timers[] = Timer::tick($interval, $fn, $arguments, $this->started);
    }

    public function after(float $after, callable $fn, array $arguments = []): Timer
    {
        return $this->timers[] = Timer::after($after, $fn, $arguments, $this->started);
    }

}