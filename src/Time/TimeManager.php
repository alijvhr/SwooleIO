<?php

namespace SwooleIO\Time;

use OpenSwoole\Timer as SwooleTimer;

class TimeManager implements \ArrayAccess
{

    protected static ?int $id;
    protected static int $time = 0;
    /** @var Timer[] $timers */
    protected array $timers;

    public static function init(): void
    {
        if (!isset(self::$id))
            self::$id = SwooleTimer::tick(100, fn() => self::$time++);
    }

    public function tick(string $name, int $interval, callable $fn): Timer
    {
        if (isset($this->timers[$name])) $this->timers[$name]->stop();
        return $this->timers[$name] = Timer::tick($interval, $fn);
    }

    public function stop(string $name = null): bool
    {
        if (!isset($name)) {
            foreach ($this->timers as $timer)
                $timer->stop();
            return true;
        }
        if (!isset($this->timers[$name])) return false;
        $this->timers[$name]->stop();
        return true;
    }

    public static function end(): void
    {
        self::$id = null;
        SwooleTimer::clearall();
    }

    public static function now(): float|int
    {
        return self::$time * 100;
    }

    public function after(string $name, int $after, callable $fn): Timer
    {
        if (isset($this->timers[$name])) $this->timers[$name]->stop();
        return $this->timers[$name] = Timer::after($after, $fn);
    }

    public function tickAfter(string $name, int $after, int $interval, callable $fn): Timer
    {
        if (isset($this->timers[$name])) $this->timers[$name]->stop();
        return $this->timers[$name] = Timer::tickAfter($after, $interval, $fn);
    }

    public function refresh(string $name = null): bool
    {
        if (!isset($name)) {
            foreach ($this->timers as $timer)
                $timer->refresh();
            return true;
        }
        if (!isset($this->timers[$name])) return false;
        $this->timers[$name]->refresh();
        return true;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->timers[$offset]);
    }

    public function offsetGet(mixed $offset): Timer
    {
        return $this->timers[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($value instanceof Timer) {
            if (isset($this->timers[$offset])) $this->timers[$offset]->stop();
            $this->timers[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->clear($offset);
    }

    public function clear(string $name = null): bool
    {
        if ($this->stop($name)) {
            if (!isset($name))
                $this->timers = [];
            else
                unset($this->timers[$name]);
            return true;
        }
        return false;
    }

    public function start(string $name = null): bool
    {
        if (!isset($name)) {
            foreach ($this->timers as $timer)
                $timer->start();
            return true;
        }
        if (!isset($this->timers[$name])) return false;
        return true;
    }

    public function remaining(string $name): int
    {
        return isset($this->timers[$name]) ? $this->timers[$name]->remaining() : 0;
    }

    public function active(string $name): int
    {
        return isset($this->timers[$name]) ? $this->timers[$name]->active() : 0;
    }

    public function elapsed(string $name): int
    {
        return isset($this->timers[$name]) ? $this->timers[$name]->elapsed() : 0;
    }
}