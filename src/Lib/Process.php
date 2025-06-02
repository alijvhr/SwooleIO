<?php

namespace SwooleIO\Lib;

use Swoole\Process\Pool;
use Swoole\Server;

abstract class Process extends Singleton
{

    protected Pool|Server $container;
    protected ?int $workerID;
    protected mixed $data;

    final protected function init(...$args): void
    {
        $this->container = $args[0];
        $this->workerID = $args[1] ?? null;
        $this->data = $args[2] ?? null;
        $this->start();
    }

    abstract public function start();

    abstract public function exit();

    abstract public function stop();

}