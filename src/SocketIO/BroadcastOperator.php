<?php

namespace SwooleIO\SocketIO;

use SwooleIO\Constants\SioPacketType;
use SwooleIO\Lib\Builder;

class BroadcastOperator extends Builder
{

    protected array $rooms;

    protected Nsp $namespace;
    protected array $excepts;

    protected float $timeout;
    protected array $flags = [
        'volatile' => false,
        'local' => false,
        'compress' => false,
    ];

    public function __construct(Nsp $namespace)
    {
        $this->namespace = $namespace;
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


    public function emit(string $event, ...$params): void
    {
        $packet = Packet::create(SioPacketType::event, $event, ...$params);
        $this->broadcast($packet);
    }


    public function broadcast(Packet $packet): void
    {
        go(function(){
            //TODO: Implement this
        }, $packet);
    }
    public function emitWithAck()
    {

    }
    public function fetchSockets()
    {

    }
    public function socketsJoin()
    {

    }
    public function socketsLeave()
    {

    }

    public function disconnectSockets(bool $close = true): void
    {

    }

    public function compress(bool $compress): self
    {
        //TODO: Implement this
        return $this;
    }

    public function volatile(): self
    {
        //TODO: Implement this
        return $this;
    }

    public function local(): self
    {
        //TODO: Implement this
        return $this;
    }


}