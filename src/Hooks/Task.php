<?php

namespace SwooleIO\Hooks;

use Swoole\Server;
use SwooleIO\EngineIO\Connection;
use SwooleIO\Lib\Hook;
use SwooleIO\Service\Packet\ServicePacket;
use SwooleIO\VO\Event;

class Task extends Hook
{

    public function onPipeMessage(Server $server, int $workerID, $data): void
    {
        $data = @unserialize($data);
        if (is_object($data)) {
            ($this->target instanceof Server ? io() : $this->target)->dispatch(new Event(ServicePacket::class, $data));
        } elseif (is_array($data)) {
            Connection::recover($data[1])?->push($data[2]);
        }
    }

    public function onTask(Server $server, Server\Task $task): void
    {
        /** @var \SwooleIO\Lib\Task $data */
        $data = $task->data;
        $data->do($server, [$task, 'finish']);
    }

    public function onFinish(Server $server, Server\TaskResult $result): void
    {
        /** @var \SwooleIO\Lib\Task $data */
        $data = $result->data;
        $data->do($server);
    }
}