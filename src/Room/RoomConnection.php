<?php

namespace SwooleIO\Psr\Middleware\Psr\Middleware\Http\Websocket\Rooms;

use SwooleIO\Psr\Middleware\Psr\Middleware\Http\Controllers\RoomController;

/**
 * @method create(int $id, array $options, string $room)
 */
class RoomConnection
{
    protected int $worker;

    public function __construct(protected $id)
    {
        $this->worker = $this->id % config('swoole_http.server.options.task_worker_num');
    }

    public function __call(string $name, array $arguments): mixed
    {
        dump($arguments);
        return app('swoole.server')->taskwait(['method' => RoomController::class . '@' . $name, 'data' => [...$arguments, 'id' => $this->id]], $this->getWorker());
    }

    /**
     * @return mixed
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getWorker(): int
    {
        return $this->worker;
    }
}