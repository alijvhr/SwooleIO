<?php

namespace SwooleIO\IO;

use OpenSwoole\Server;
use SwooleIO\Lib\EventHook;

class Task extends EventHook
{

    public function onTask(Server $server, Server\Task $task): void
    {
        /** @var \SwooleIO\Lib\Task $data */
        $data = $task->data;
        $data->start($server);
    }
}