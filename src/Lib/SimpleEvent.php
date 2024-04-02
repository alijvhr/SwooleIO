<?php

namespace SwooleIO\Lib;

use SwooleIO\Psr\Event\Event;

class SimpleEvent extends Event
{
    public function __construct(public string $type, public $data = null)
    {
    }
}