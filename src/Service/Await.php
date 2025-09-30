<?php

namespace SwooleIO\Service;

use OpenSwoole\Coroutine;
use SwooleIO\Service\Packet\Exception;
use SwooleIO\Service\Packet\ServicePacket;
use SwooleIO\Time\TimeManager;

class Await
{

    public int $expire;
    public ServicePacket|null $result = null;

    public function __construct(public int $cid, int $timeout)
    {
        $this->expire = TimeManager::now() + $timeout;
    }

    public function set(ServicePacket|null $packet): void
    {
        $this->result = $packet;
        Coroutine::resume($this->cid);
    }

    public function get(): mixed
    {
        if ($this->result instanceof Exception) throw $this->result->get();
        return $this->result?->get();
    }

}