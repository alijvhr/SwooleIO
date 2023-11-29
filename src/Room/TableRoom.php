<?php

namespace SwooleIO\Psr\Middleware\Psr\Middleware\Http\Websocket\Rooms;

use InvalidArgumentException;
use Swoole\Table;
use SwooleIO\Psr\Middleware\Psr\Middleware\Http\Table\SwooleTable;

class TableRoom implements RoomContract
{

    /**
     * @var Table
     */
    protected Table $rooms, $params;
    /**
     * @var int[]
     */
    protected array $subscribers = [];

    /**
     * TableRooms constructor.
     */
    public function __construct(protected int $id)
    {
    }

    public function restore()
    {
        $this->connectTables();
    }

    private function connectTables()
    {
        /** @var SwooleTable $table */
        $table = app('swoole.table');
        $this->rooms = $table->get('swoole.rooms');
        $this->params = $table->get('swoole.params');
    }

    /**
     * Add a socket fd to current room.
     *
     * @param  int  $fd
     * @param  int  $userId
     *
     */
    public function subscribe(int $fd, int $userId)
    {
        $this->subscribers[$fd] = $userId;
        $fds = array_keys($this->subscribers);
        $this->rooms->set($this->id, ['subscribers' => implode($fds)]);
    }

    /**
     * Delete a socket fd from current room.
     *
     * @param  int  $fd
     *
     */
    public function unsubscribe(int $fd)
    {
    }

    /**
     * Get all sockets by a room key.
     *
     * @param  string room
     *
     * @return array
     */
    public function getClients(string $room)
    {
        return $this->getValue($room, RoomContract::ROOMS_KEY) ?? [];
    }

    /**
     * Get all rooms by a fd.
     *
     * @param  int fd
     *
     * @return array
     */
    public function getRooms(int $fd)
    {
        return $this->getValue($fd, RoomContract::DESCRIPTORS_KEY) ?? [];
    }

    /**
     * @param  string  $room
     * @param  array  $fds
     *
     * @return TableRoom
     */
    protected function setClients(string $room, array $fds): TableRoom
    {
        return $this->setValue($room, $fds, RoomContract::ROOMS_KEY);
    }

    /**
     * @param  int  $fd
     * @param  array  $rooms
     *
     * @return TableRoom
     */
    protected function setRooms(int $fd, array $rooms): TableRoom
    {
        return $this->setValue($fd, $rooms, RoomContract::DESCRIPTORS_KEY);
    }

    /**
     * Init rooms table
     */
    protected function initRoomsTable(): void
    {
        $this->rooms = new Table($this->config['room_rows']);
        $this->rooms->column('value', Table::TYPE_STRING, $this->config['room_size']);
        $this->rooms->create();
    }

    /**
     * Init descriptors table
     */
    protected function initFdsTable()
    {
        $this->fds = new Table($this->config['client_rows']);
        $this->fds->column('value', Table::TYPE_STRING, $this->config['client_size']);
        $this->fds->create();
    }

    /**
     * Set value to table
     *
     * @param $key
     * @param  array  $value
     * @param  string  $table
     *
     * @return $this
     */
    public function setValue($key, array $value, string $table)
    {
        $this->checkTable($table);

        $this->$table->set($key, ['value' => json_encode($value)]);

        return $this;
    }

    /**
     * Get value from table
     *
     * @param  string  $key
     * @param  string  $table
     *
     * @return array|mixed
     */
    public function getValue(string $key, string $table)
    {
        $this->checkTable($table);

        $value = $this->$table->get($key);

        return $value ? json_decode($value['value'], true) : [];
    }

    /**
     * Check table for exists
     *
     * @param  string  $table
     */
    protected function checkTable(string $table)
    {
        if (!property_exists($this, $table) || !$this->$table instanceof Table) {
            throw new InvalidArgumentException("Invalid table name: `{$table}`.");
        }
    }
}
