<?php

namespace SwooleIO\SocketIO;

use SwooleIO\EngineIO\Adapter;
use SwooleIO\Lib\Builder;

class BroadcastOperator extends Builder
{

    protected array $rooms;

    protected Route $namespace;
    protected Adapter $adapter;
    protected array $excepts;

    protected float $timeout;
    protected array $flags = [
        'volatile' => false,
        'local' => false,
        'compress' => false,
    ];

    public function __construct(Route $namespace, Adapter $adapter)
    {
        $this->namespace = $namespace;
        $this->adapter = $adapter;
    }

    public function in(string ...$rooms): self
    {
        return $this->to(...$rooms);
    }

    public function to(string ...$rooms): self
    {
        $this->rooms = array_unique($rooms + $this->rooms);
        return $this;
    }

    public function except(string ...$rooms): self
    {
        $this->excepts = array_unique($rooms + $this->excepts);
        return $this;
    }

    public function flag(string ...$flags): self
    {
        foreach ($flags as $flag)
            $this->flags[$flag] = true;
        return $this;
    }

    public function unflag(string ...$flags): self
    {
        foreach ($flags as $flag)
            $this->flags[$flag] = false;
        return $this;
    }

    public function timeout(float $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }


    public function emit(string $event, ...$params): boolean
    {

        $packet = Packet::create('event', ...$params);

        $withAck = is_callable($params[count($params) - 1]);
        if (!$withAck) return $this->broadcast($packet);
        $callback = array_pop($params);
        return $this->broadcast($packet, $callback);
    }

    /**
     * Emits an event and waits for an acknowledgement from all clients.
     *
     * @return a Promise that will be fulfilled when all clients have acknowledged the event
     * @example
     * try {
     *   const responses = await io.timeout(1000).emitWithAck("some-event");
     *   console.log(responses); // one response per client
     * } catch (e) {
     *   // some clients did not acknowledge the event in the given delay
     * }
     *
     */
    public function emitWithAck()
    {

    }

    /**
     * Gets a list of clients.
     *
     * @deprecated this method will be removed in the next major release, please use {@link Server#serverSideEmit} or
     * {@link fetchSockets} instead.
     */
    public function allSockets()
    {
    }

    /**
     * Returns the matching socket instances. This method works across a cluster of several Socket.IO servers.
     *
     * Note: this method also works within a cluster of multiple Socket.IO servers, with a compatible {@link Adapter}.
     *
     * @example
     * // return all Socket instances
     * const sockets = await io.fetchSockets();
     *
     * // return all Socket instances in the "room1" room
     * const sockets = await io.in("room1").fetchSockets();
     *
     * for (const socket of sockets) {
     *   console.log(socket.id);
     *   console.log(socket.handshake);
     *   console.log(socket.rooms);
     *   console.log(socket.data);
     *
     *   socket.emit("hello");
     *   socket.join("room1");
     *   socket.leave("room2");
     *   socket.disconnect();
     * }
     */
    public function fetchSockets()
    {

    }

    /**
     * Makes the matching socket instances join the specified rooms.
     *
     * Note: this method also works within a cluster of multiple Socket.IO servers, with a compatible {@link Adapter}.
     *
     * @param room - a room, or an array of rooms
     * @example
     *
     * // make all socket instances join the "room1" room
     * io.socketsJoin("room1");
     *
     * // make all socket instances in the "room1" room join the "room2" and "room3" rooms
     * io.in("room1").socketsJoin(["room2", "room3"]);
     *
     */
    public function socketsJoin()
    {

    }

    /**
     * Makes the matching socket instances leave the specified rooms.
     *
     * Note: this method also works within a cluster of multiple Socket.IO servers, with a compatible {@link Adapter}.
     *
     * @param room - a room, or an array of rooms
     * @example
     * // make all socket instances leave the "room1" room
     * io.socketsLeave("room1");
     *
     * // make all socket instances in the "room1" room leave the "room2" and "room3" rooms
     * io.in("room1").socketsLeave(["room2", "room3"]);
     *
     */
    public function socketsLeave()
    {

    }

    /**
     * Makes the matching socket instances disconnect.
     *
     * Note: this method also works within a cluster of multiple Socket.IO servers, with a compatible {@link Adapter}.
     *
     * @param close - whether to close the underlying connection
     * @example
     * // make all socket instances disconnect (the connections might be kept alive for other namespaces)
     * io.disconnectSockets();
     *
     * // make all socket instances in the "room1" room disconnect and close the underlying connections
     * io.in("room1").disconnectSockets(true);
     *
     */
    public function disconnectSockets(bool $close = true): void
    {

    }


}