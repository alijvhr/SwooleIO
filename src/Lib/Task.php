<?php

namespace SwooleIO\Lib;

use OpenSwoole\Server;

abstract class Task
{

    abstract public function start(Server $server): string;

}