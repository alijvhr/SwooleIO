<?php

namespace SwooleIO\Process;

use SwooleIO\Psr\Event\Event;

class PipeEvent extends Event
{
    public function __construct(public string $type, public $data)
    {
    }
}