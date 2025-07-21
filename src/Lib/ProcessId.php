<?php

namespace SwooleIO\Lib;

class ProcessId
{

    public function __construct(public readonly string $server,
                                public string          $service,
                                public int|string      $worker = 0)
    {
    }

    public function __toString(): string
    {
        return "$this->service#$this->worker:$this->server";
    }

}