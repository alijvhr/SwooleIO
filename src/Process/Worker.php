<?php

namespace SwooleIO\Process;

use Sparrow\Lib\Service\Async;
use Sparrow\Lib\Service\Packet\ServicePacket;
use Swoole\Event;
use SwooleIO\EngineIO\Connection;
use SwooleIO\Lib\Process;
use SwooleIO\Lib\SimpleEvent;
use SwooleIO\Time\TimeManager;
use function SwooleIO\debug;
use function SwooleIO\io;

class Worker extends Process
{

    public function start(): void
    {
        $type = ($this->container->taskworker ? 'task-worker' : 'worker');
        $io = io();
        $io->id($type, $io->server()->worker_id);
        $io->log->info("{$io->id()} started");
        $io->dispatch(new SimpleEvent('workerStart'));
        $io->tick(10, fn() => gc_collect_cycles());
        $io->on(ServicePacket::class, fn(SimpleEvent $event) => Async::setById($event->data->id, $event->data));
    }

    public function exit(): void
    {
        $type = ($this->container->taskworker ? 'task-worker' : 'worker');
        io()->log->info("$type #$this->workerID exit");
        Connection::saveAll();
        TimeManager::end();
        Event::Exit();
    }

    public function error(): void
    {
        $pid = getmypid();
        debug("error on $pid");
    }

    public function stop(): void
    {
//        $type = ($this->container->taskworker ? 'task-worker' : 'worker');
//        io()->log->info("$type #$this->workerID stopped");
        io()->dispatch(new SimpleEvent('workerStop'));
    }
}