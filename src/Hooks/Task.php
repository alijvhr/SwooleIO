<?php

namespace SwooleIO\Hooks;

use OpenSwoole\Server;
use SwooleIO\EngineIO\Connection;
use SwooleIO\Lib\Hook;
use SwooleIO\Process\PipeEvent;
use SwooleIO\Psr\Event\Event;

class Task extends Hook
{

    public function onPipeMessage(Server $server, int $workerID, $data): void
    {
        $data = unserialize($data);
        if ($data[0] == 'send') {
            Connection::recover($data[1])?->push($data[2]);
        } else {
            $this->target->dispatch(new PipeEvent($data[0], $data));
        }
    }

    public function onTask(Server $server, Server\Task $task): void
    {
        /** @var \SwooleIO\Lib\Task $data */
        $data = $task->data;
        $data->do($server, [$task,'finish']);
    }

    public function onFinish(Server $server, Server\TaskResult $result): void
    {
        /** @var \SwooleIO\Lib\Task $data */
        $data = $result->data;
        $data->do($server);
    }
}