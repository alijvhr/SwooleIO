<?php

namespace SwooleIO\Service\Packet;

use SwooleIO\Service\ServiceProxy;
use function SwooleIO\io;

abstract class ServicePacket
{

    private static int $index = 0;
    public readonly ServiceProxy $from;
    public string $id;

    public function __construct(public readonly ServiceProxy $to, public array $data = [], string|int|null $id = null, ServiceProxy|null $from = null)
    {
        $this->id = $id ?? self::$index++;
        $pid = io()->id();
        $this->from = ServiceProxy::for($pid->service, $pid->worker, $pid->server);
    }

    public function get(int $offset = 0): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public static function for(ServicePacket|Call $packet, mixed $data): static
    {
        return new static($packet->from, $data, $packet->id, $packet->to);
    }

    public function __toString(): string
    {
        return serialize($this);
    }

}