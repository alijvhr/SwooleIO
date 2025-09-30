<?php

namespace SwooleIO\Cron;

class UnitValue
{

    /**
     * @param int $from
     * @param int|null $through
     * @param int|null $step
     */
    public function __construct(public string $name, public int $from = 0, public ?int $through = null, public int $step = 0) { }


}