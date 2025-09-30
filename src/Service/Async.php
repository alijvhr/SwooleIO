<?php

namespace SwooleIO\Service;

use Swoole\Coroutine;
use SwooleIO\Service\Packet\Exception;
use SwooleIO\Service\Packet\ServicePacket;
use SwooleIO\Time\Timer;

class Async
{

    public Timer $timer;
    public ServicePacket|null $result = null;
    public int $cid;

    /** @var static[] $queue */
    protected static array $queue = [];

    public function __construct(float $timeout, public readonly int|string|null $id = null)
    {
        $this->timer = Timer::after($timeout, fn() => $this->set(null));
    }

    public function set(ServicePacket|null $packet): void
    {
        $this->result = $packet;
        $this->timer->stop();
        if (isset($this->id))
            unset(self::$queue[$this->id]);
        if (isset($this->cid)) {
            if (Coroutine::exists($this->cid))
                Coroutine::resume($this->cid);
        }
    }

    public static function setById(int|string $id, ServicePacket|null $packet): void
    {
        if (isset(self::$queue[$id])) {
            self::$queue[$id]->set($packet);
        }
    }

    public static function wait(int|string $id, float $timeout): static
    {
        return self::$queue[$id] = new static($timeout, $id);
    }

    public function get(): mixed
    {
        if (!isset($this->result)) {
            $this->cid = Coroutine::getCid();
            Coroutine::yield();
        }
        if ($this->result instanceof Exception) throw $this->result->get();
        return $this->result?->get();
    }

}