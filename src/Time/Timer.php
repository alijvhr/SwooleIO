<?php

namespace SwooleIO\Time;

use Closure;
use Swoole\Event;
use Swoole\Timer as SwooleTimer;
use SwooleIO\Constants\TimerType;
use TypeError;

class Timer
{

    protected int $elapsed = 0;
    protected float $start;
    protected ?int $id = null;
    /** @var callable():void $fn */
    protected $fn;

    protected function __construct(protected TimerType $type, callable|array $fn, protected int $after = 0, protected int $interval = 0, protected array $arguments = [])
    {
        $this->fn = $fn;
    }

    /**
     * @param float $seconds
     * @param callable():void $fn
     * @param array $arguments
     * @param bool $start
     * @return static
     */
    public static function after(float $seconds, callable|array $fn, array $arguments = [], bool $start = true): static
    {
        return self::create($fn, $seconds, 0, $arguments, $start);
    }

    /**
     * @param callable|array $fn
     * @param array $arguments
     * @param bool $start
     * @return static
     */
    public static function defer(callable|array $fn, array $arguments = [], bool $start = true): static
    {
        return self::create($fn, 0, 0, $arguments, $start);
    }

    /**
     * @param float $seconds
     * @param callable():void $fn
     * @param array $arguments
     * @param bool $start
     * @return static
     */
    public static function tick(float $seconds, callable|array $fn, array $arguments = [], bool $start = true): static
    {
        return self::create($fn, 0, $seconds, $arguments, $start);

    }

    /**
     * @param float $after
     * @param float $interval
     * @param callable():void $fn
     * @param array $arguments
     * @param bool $start
     * @return static
     */
    public static function tickAfter(float $after, float $interval, callable|array $fn, array $arguments = [], bool $start = true): static
    {
        return self::create($fn, $after, $interval, $arguments, $start);
    }

    /**
     * @param callable|array $fn
     * @param float $after
     * @param float $interval
     * @param array $arguments
     * @param bool $start
     * @return static
     */
    public static function create(callable|array $fn, float $after, float $interval, array $arguments, bool $start): Timer
    {
        $after = round($after, 3);
        $interval = round($interval, 3);
        $type = match (0.0) {
            $after + $interval => TimerType::defer,
            $after             => TimerType::interval,
            $interval          => TimerType::timeout,
            default            => TimerType::hybrid,
        };

        if (!is_callable($fn)) throw new TypeError('The argument $fn should be a callable.');
        $timer = new static($type, $fn, round($after * 1000), round($interval * 1000), $arguments);
        if ($start)
            $timer->start();
        return $timer;
    }

    public function active(): bool
    {
        return isset($this->id);
    }

    public function stop(): void
    {
        if (!isset($this->id)) return;
        $this->elapsed();
        if ($this->type != TimerType::defer)
            SwooleTimer::clear($this->id);
        $this->id = null;
    }

    public function elapsed(bool $asMilliseconds = false): float|int
    {
        return ($this->elapsed = TimeManager::now() - $this->start) / ($asMilliseconds ? 1 : 1000);
    }

    public function refresh(): void
    {
        if ($this->type != TimerType::defer)
            SwooleTimer::clear($this->id);
        $this->elapsed = 0;
        $this->start();
    }

    public function start(): void
    {
        if (!is_callable($this->fn) || SwooleTimer::exists($this->id ?? -1)) return;
        $this->start ??= TimeManager::now();
        $args = match ($this->type) {
            TimerType::timeout, TimerType::defer   => [$this->type, $this->fn],
            TimerType::interval, TimerType::hybrid => [TimerType::timeout, function () {
                ($this->fn)($this, ...$this->arguments);
                $this->timer(TimerType::interval, $this->fn);
            }]
        };
        $this->timer(...$args);
    }

    public function remaining(bool $asMilliseconds = false): float|int
    {
        if ($this->type == TimerType::defer) return 0;
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
            'type'      => $this->type,
            'after'     => $this->after,
            'interval'  => $this->interval,
            'arguments' => $this->arguments,
            'elapsed'   => $this->elapsed,
            'time'      => round(microtime(true), 3),
            'start'     => $this->start,
            'id'        => $this->id ?? null,
            'fn'        => $fn,
        ];
    }

    public function __unserialize(array $data): void
    {
        [
            'type'      => $this->type,
            'after'     => $this->after,
            'interval'  => $this->interval,
            'arguments' => $this->arguments,
            'elapsed'   => $this->elapsed,
            'fn'        => $this->fn,
        ] = $data;
        $diff = round(microtime(true), 3) - $data['time'];
        $this->start = max(-$data['elapsed'] - $diff, 0);
        if ($data['id'] && is_callable($this->fn)) {
            $this->start();
        }
    }

    protected function timer(TimerType $type, callable|array $fn): void
    {
        $callback = function () use ($fn) {
            $fn($this, ...$this->arguments);
            if (in_array($this->type, [TimerType::defer, TimerType::timeout])) $this->stop();
        };
        $this->id = match ($type) {
            TimerType::defer    => Event::defer($callback),
            TimerType::interval => SwooleTimer::tick($this->interval, $callback),
            default             => SwooleTimer::after($this->remaining(true), $callback)
        };
    }

    public function info(): ?array
    {
        return SwooleTimer::exists($this->id ?? -1) ? SwooleTimer::info($this->id) : null;
    }

    protected function calculate(TimerType $type, int $now): int
    {
        return $type == TimerType::timeout ? $this->after - $now : $this->interval - $now % $this->interval;
    }

}