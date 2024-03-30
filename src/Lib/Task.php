<?php

namespace SwooleIO\Lib;

use OpenSwoole\Server;

abstract class Task
{

    abstract public function do(Server $server, callable $finish = null): void;

    public function __toString(): string
    {
        return serialize($this);
    }

}