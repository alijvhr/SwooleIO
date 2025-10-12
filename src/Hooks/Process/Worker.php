<?php

namespace SwooleIO\Hooks\Process;

use SwooleIO\EngineIO\Connection;
use SwooleIO\Hooks\Process;
use SwooleIO\Service\Async;
use SwooleIO\Service\Packet\ServicePacket;
use SwooleIO\VO\Event;

class Worker extends Process
{

    public function onWorkerStart(): void
    {
        $this->io->id(service: 'worker', worker: $this->io->server->worker_id);
        $this->log('started');
        $this->io->dispatch(new Event('workerStart'));
        $this->io->tick(10, static fn() => gc_collect_cycles());
        $this->io->on(
            ServicePacket::class,
            static fn(Event $event) => Async::setById($event->data->id, $event->data)
        );
    }

    public function onWorkerExit(): void
    {
        $this->log('exited');
        Connection::saveAll();
        $this->exit();
    }

    public function onWorkerStop(): void
    {
        $this->log('stopped');
        io()->dispatch(new Event('workerStop'));
    }
}