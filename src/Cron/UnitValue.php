<?php

namespace SwooleIO\Cron;

class UnitValue
{

    protected(set) string $symbol;

    protected(set) string $unit;

    protected(set) string $max;

    /**
     * @param int $from
     * @param int|null $through
     * @param int|null $step
     */
    public function __construct(public string $name, public int $from = 0, public ?int $through = null, public int $step = 0)
    {
        $this->unit = match ($this->name) {
            'second'  => 'seconds',
            'minute'  => 'minutes',
            'hour'    => 'hours',
            'day',
            'weekday' => 'days',
            'month'   => 'months',
            default   => throw new \InvalidArgumentException("Invalid unit name: {$this->name}"),
        };
        $this->symbol = match ($this->name) {
            'second'  => 's',
            'minute'  => 'i',
            'hour'    => 'H',
            'day'     => 'd',
            'weekday' => 'w',
            'month'   => 'm',
            default   => throw new \InvalidArgumentException("Invalid unit name: {$this->name}"),
        };
        $this->parent = match ($this->name) {
            'second'  => 'minutes',
            'minute'  => 'hours',
            'hour'    => 'days',
            'day'     => 'months',
            'weekday' => 'weeks',
            'month'   => 'years',
            default   => throw new \InvalidArgumentException("Invalid unit name: {$this->name}"),
        };
    }


}