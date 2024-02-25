<?php

namespace SwooleIO\Lib;

use OpenSwoole\Process\Pool;
use OpenSwoole\Server;

abstract class Process extends Singleton
{

    protected Pool|Server $container;
    protected int $workerID;
    protected mixed $data;

    final public static function start(Pool|Server $container, int $workerID, mixed $data = null): static
    {
        $process = static::instance(false);
        $process->container = $container;
        $process->workerID = $workerID;
        $process->data = $data;
        $process->init();
        return $process;
    }

    abstract public function exit();

}