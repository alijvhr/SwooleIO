<?php

namespace SwooleIO\Hooks;

use OpenSwoole\Server;
use SwooleIO\EngineIO\Connection;
use SwooleIO\Lib\Hook;

class Task extends Hook
{

    public function onPipeMessage(Server $server, int $workerID, $data): void
    {
        $data = unserialize($data);
        switch ($data[0]) {
            case 'send':
                Connection::recover($data[1])?->push($data[2]);
        }
    }

    public function onTask(Server $server, Server\Task $task): void
    {
        /** @var \SwooleIO\Lib\Task $data */
        $data = $task->data;
        $data->do($server);
    }

    public function onFinish(Server $server, Server\TaskResult $result): void
    {
        /** @var \SwooleIO\Lib\Task $data */
        $data = $result->data;
        $data->do($server);
    }
}