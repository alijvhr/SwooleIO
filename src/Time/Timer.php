<?php

namespace SwooleIO\Time;

use Closure;
use OpenSwoole\Timer as SwooleTimer;
use SwooleIO\Constants\TimerType;
use function SwooleIO\io;

class Timer
{

    protected int $elapsed = 0;
    protected int $start;
    protected ?int $id;
    /** @var callable(static):void $fn */
    protected $fn;

    protected function __construct(protected TimerType $type, callable $fn, protected int $after = 0, protected int $interval = 0, protected array $arguments = [])
    {
        TimeManager::init();
        $this->fn = $fn;
        $this->arguments = $arguments;
        $this->start = TimeManager::now();
    }

    /**
     * @param float $after
     * @param callable(static):void $fn
     * @param array $arguments
     * @return static
     */
    public static function after(float $after, callable $fn, array $arguments = []): static
    {
        $timer = new static(TimerType::timeout, $fn, after: $after * 1000, arguments: $arguments);
        $timer->start();
        return $timer;
    }

    /**
     * @param float $interval
     * @param callable(static):void $fn
     * @param array $arguments
     * @return static
     */
    public static function tick(float $interval, callable $fn, array $arguments = []): static
    {
        $timer = new static(TimerType::interval, $fn, interval: $interval * 1000, arguments: $arguments);
        $timer->start();
        return $timer;

    }

    /**
     * @param float $after
     * @param float $interval
     * @param callable(static):void $fn
     * @param array $arguments
     * @return static
     */
    public static function tickAfter(float $after, float $interval, callable $fn, array $arguments = []): static
    {
        $timer = new static(TimerType::timeout, $fn, $after * 1000, $interval * 1000, arguments: $arguments);
        $timer->start();
        return $timer;
    }

    public function active(): bool
    {
        return isset($this->id);
    }

    public function stop(): void
    {
        $this->elapsed();
        SwooleTimer::clear($this->id);
        $this->id = null;
    }

    public function elapsed(bool $asMilliseconds = false): float|int
    {
        return ($this->elapsed = TimeManager::now() - $this->start) / ($asMilliseconds ? 1 : 1000);
    }

    public function refresh(): void
    {
        SwooleTimer::clear($this->id);
        $this->elapsed = 0;
        $this->start();
    }

    public function start(): void
    {
        if (!is_callable($this->fn)) return;
        $this->start = TimeManager::now();
        if ($this->type == TimerType::timeout)
            $this->timer(TimerType::timeout, $this->fn);
        else
            $this->timer(TimerType::timeout, function () {
                ($this->fn)($this, ...$this->arguments);
                $this->timer(TimerType::interval, $this->fn);
            });
    }

    public function remaining(bool $asMilliseconds = false): float|int
    {
        $this->elapsed();
        if ($this->type == TimerType::hybrid)
            return $this->calculate(...$this->elapsed < $this->after ? [TimerType::timeout, $this->elapsed] : [TimerType::interval, $this->elapsed - $this->after]);
        return $this->calculate($this->type, $this->elapsed) / ($asMilliseconds ? 1 : 1000);
    }

    public function __serialize(): array
    {
        $this->elapsed();
        $fn = $this->fn instanceof Closure ? null : $this->fn;
        return [
            'type' => $this->type->value,
            'after' => $this->after,
            'interval' => $this->interval,
            'arguments' => $this->arguments,
            'elapsed' => $this->elapsed,
            'start' => $this->start,
            'id' => $this->id,
            'fn' => $fn
        ];
    }

    public function __unserialize(array $data): void
    {
        [
            'type' => $type,
            'after' => $this->after,
            'interval' => $this->interval,
            'arguments' => $this->arguments,
            'elapsed' => $this->elapsed,
            'start' => $this->start,
            'id' => $this->id,
            'fn' => $this->fn
        ] = $data;
        $this->type = TimerType::tryFrom($type);
        if ($this->id) $this->start();
    }

    protected function timer(TimerType $type, callable $fn): void
    {
        $this->id = io()->{$type->value}($this->remaining(true), fn() => $fn($this, ...$this->arguments));
    }

    protected function call(callable $fn, array $arguments = []): void
    {
//        try {
//            $reflection = new ReflectionFunction($fn);
//            $reflection->
//        } catch (ReflectionException $e) {
//        }
    }

    protected function calculate(TimerType $type, int $now): int
    {
        return $type == TimerType::timeout ? $this->after - $now : $this->interval - $now % $this->interval;
    }

}