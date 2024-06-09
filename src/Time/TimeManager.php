<?php

namespace SwooleIO\Time;

use ArrayAccess;
use OpenSwoole\Timer as SwooleTimer;
use function SwooleIO\io;

class TimeManager implements ArrayAccess
{

    protected static ?int $id;
    protected static int $time = 0;
    /** @var Timer[] $timers */
    protected array $timers = [];

    public static function init(): void
    {
        if (!isset(self::$id))
            self::$id = io()->tick(100, fn() => self::$time++);
    }

    public function tick(string $name, float $interval, callable $fn, array $arguments = []): Timer
    {
        if (isset($this->timers[$name])) $this->timers[$name]->stop();
        return $this->timers[$name] = Timer::tick($interval, $fn, $arguments);
    }

    public function stop(string $name = null): bool
    {
        self::init();
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
        self::init();
        self::$id = null;
        SwooleTimer::clearall();
    }

    public static function now(): float|int
    {
        self::init();
        return self::$time * 100;
    }

    public function after(string $name, float $after, callable $fn, array $arguments = []): Timer
    {
        if (isset($this->timers[$name])) $this->timers[$name]->stop();
        return $this->timers[$name] = Timer::after($after, $fn, $arguments);
    }

    public function tickAfter(string $name, float $after, float $interval, callable $fn, array $arguments = []): Timer
    {
        if (isset($this->timers[$name])) $this->timers[$name]->stop();
        return $this->timers[$name] = Timer::tickAfter($after, $interval, $fn, $arguments);
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

    public function remaining(string $name, bool $asMilliseconds = false): float|int
    {
        return isset($this->timers[$name]) ? $this->timers[$name]->remaining($asMilliseconds) : 0;
    }

    public function active(string $name): int
    {
        return isset($this->timers[$name]) ? $this->timers[$name]->active() : 0;
    }

    public function elapsed(string $name, bool $asMilliseconds = false): float|int
    {
        return isset($this->timers[$name]) ? $this->timers[$name]->elapsed($asMilliseconds) : 0;
    }
}