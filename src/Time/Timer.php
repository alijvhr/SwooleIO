<?php

namespace SwooleIO\Time;

use OpenSwoole\Timer as SwooleTimer;
use SwooleIO\Constants\TimerType;

class Timer
{

    protected int $elapsed = 0;
    protected int $start;
    protected ?int $id;
    protected $fn;

    protected function __construct(protected TimerType $type, callable $fn, protected int $after = 0, protected int $interval = 0)
    {
        TimeManager::init();
        $this->fn = $fn;
    }

    public static function after(int $after, callable $fn): static
    {
        return new static(TimerType::timeout, $fn, after: $after);
    }

    public static function tick(int $interval, callable $fn): static
    {
        return new static(TimerType::timeout, $fn, interval: $interval);

    }

    public static function tickAfter(int $after, int $interval, callable $fn): static
    {
        return new static(TimerType::timeout, $fn, after: $after, interval: $interval);
    }

    public function active():bool
    {
        return isset($this->id);
    }
    public function stop(): void
    {
        $this->elapsed();
        SwooleTimer::clear($this->id);
        $this->id = null;
    }

    public function elapsed(): int
    {
        return $this->elapsed = TimeManager::now() - $this->start;
    }

    public function refresh(): void
    {
        SwooleTimer::clear($this->id);
        $this->elapsed = 0;
        $this->start();
    }

    public function start(): void
    {
        $this->start = TimeManager::now();
        if ($this->type == TimerType::timeout)
            $this->timer(TimerType::timeout, $this->fn);
        else
            $this->timer(TimerType::timeout, function () {
                ($this->fn)();
                $this->timer(TimerType::interval, $this->fn);
            });
    }

    protected function timer(TimerType $type, callable $fn): void
    {
        $this->id = "SwooleTimer::$type->value"($this->remaining(), $fn);
    }

    public function remaining(): int
    {
        $this->elapsed();
        if ($this->type == TimerType::hybrid)
            return $this->calculate(...$this->elapsed < $this->after ? [TimerType::timeout, $this->elapsed] : [TimerType::interval, $this->elapsed - $this->after]);
        return $this->calculate($this->type, $this->elapsed);
    }

    protected function calculate(TimerType $type, int $now): int
    {
        return $type == TimerType::timeout ? $this->after - $now : $this->interval - $now % $this->interval;
    }

}