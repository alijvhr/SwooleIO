<?php

namespace SwooleIO\Psr\Middleware\Psr\Middleware\Contracts;

interface RoomContract
{

    /**
     * Descriptors key
     *
     * @const string
     */
    public const DESCRIPTORS_KEY = 'fds';

    /**
     * Add a socket fd to a room.
     *
     * @param  int fd
     * @param  int  $userIdId
     */
    public function subscribe(int $fd, int $userIdId);

    /**
     * Delete a socket fd from a room.
     *
     * @param  int fd
     * @param  int  $userId
     */
    public function unsubscribe(int $fd);

    /**
     * Get all sockets by a room key.
     *
     * @param  string room
     *
     * @return array
     */
    public function getClients(string $room);

    /**
     * Get all rooms by a fd.
     *
     * @param  int fd
     *
     * @return array
     */
    public function getRooms(int $fd);
}
