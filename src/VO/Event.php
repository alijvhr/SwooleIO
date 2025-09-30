<?php

namespace SwooleIO\VO;

use SwooleIO\Psr\Event\Event as PsrEvent;

class Event extends PsrEvent
{
    public function __construct(public string $type, public $data = null)
    {
    }
}