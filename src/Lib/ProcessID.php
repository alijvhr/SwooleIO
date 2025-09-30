<?php

namespace SwooleIO\Lib;

class ProcessID
{

    public function __construct(public readonly string $server,
                                public string          $service,
                                public int|string      $worker = 0)
    {
    }

    public function __toString(): string
    {
        $__ = $this->worker ? '#' : '';
        return "$this->server->$this->service$__$this->worker";
    }

}