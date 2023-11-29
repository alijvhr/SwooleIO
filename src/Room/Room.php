<?php

namespace SwooleIO\Psr\Middleware\Psr\Middleware\Http\Websocket\Room;

class Room
{

    protected array $fds = [];

    protected int $id;

    public function __construct(public array $props = [])
    {
        var_dump('---------IN Room--------');
        $this->set(['params.status' => 'idle']);
        var_dump($props);
        var_dump(app('swoole.server')->getWorkerId());
        var_dump('---------IN Room--------');
    }

    public static function create(array $options = []): RoomConnection
    {
        $id = Setting::incr('swooleio.room_id');
        $name = static::class;
        $connection = new RoomConnection($id);
        $connection->create(options: $options, room: static::class);
//        app('swoole.server')->task(['method' => RoomController::class . '@create', 'data' => ['id' => $id, 'options' => $options, 'room' => $name]], $connection->getWorker());
        return $connection;
    }

    public static function fetch(int $id): RoomConnection
    {
        $connection = new RoomConnection($id);
//        app('swoole.server')->task(['method' => RoomController::class . '@fetch', 'data' => ['id' => $id]], $connection->getWorker());
        return $connection->fetch();
    }
    

    public function join(string $sid, int $fd): bool
    {
        //TODO: Add to table
        return false;
    }

    public function emit(string $event, $data): void
    {
        
    }
}