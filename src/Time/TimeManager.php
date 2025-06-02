<?php

namespace SwooleIO\Time;

use ArrayAccess;
use Swoole\Timer as SwooleTimer;

class TimeManager implements ArrayAccess
{

    private static ?int $id;
    private static int $time = 0;
    private static int $hr = 0;
    /** @var Timer[] $timers */
    protected array $timers = [];

    public static function init(): void
    {
        if (!SwooleTimer::exists(self::$id ?? -1)) {
            self::$id = SwooleTimer::tick(1, fn() => self::$time++);
        }
        if (!self::$hr) self::$hr = hrtime(true);
    }

    public function tick(string|int $name, float $interval, callable|array $fn, array $arguments = []): Timer
    {
        if (isset($this->timers[$name])) $this->timers[$name]->stop();
        return $this->timers[$name] = Timer::tick($interval, $fn, $arguments);
    }

    public function stop(string|int|null $name = null): bool
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
        self::$id = null;
        SwooleTimer::clearall();
    }

    public static function now(bool $hr = false): float|int
    {
        self::init();
        return $hr ? (hrtime(true) - self::$hr) / 1e6 : self::$time;
    }

    public function after(string|int $name, float $after, callable|array $fn, array $arguments = []): Timer
    {
        if (isset($this->timers[$name])) $this->timers[$name]->stop();
        return $this->timers[$name] = Timer::after($after, $fn, $arguments);
    }

    public function tickAfter(string|int $name, float $after, float $interval, callable|array $fn, array $arguments = []): Timer
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
            if (is_null($offset))
                $this->timers[] = $value;
            else {
                if (isset($this->timers[$offset])) $this->timers[$offset]->stop();
                $this->timers[$offset] = $value;
            }
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->clear($offset);
    }

    public function clear(string|int $name = null): bool
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

    public function start(string|int $name = null): bool
    {
        if (!isset($name)) {
            foreach ($this->timers as $timer)
                $timer->start();
            return true;
        }
        if (!isset($this->timers[$name])) return false;
        $this->timers[$name]->start();
        return true;
    }

    public function remaining(string|int $name, bool $asMilliseconds = false): float|int
    {
        return isset($this->timers[$name]) ? $this->timers[$name]->remaining($asMilliseconds) : 0;
    }

    public function active(string|int $name): bool
    {
        return isset($this->timers[$name]) && $this->timers[$name]->active();
    }

    public function elapsed(string|int $name, bool $asMilliseconds = false): float|int
    {
        return isset($this->timers[$name]) ? $this->timers[$name]->elapsed($asMilliseconds) : 0;
    }
}