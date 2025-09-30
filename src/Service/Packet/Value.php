<?php

namespace SwooleIO\Service\Packet;

use SwooleIO\Service\ServiceProxy;

class Value extends ServicePacket
{
    public function __construct(ServiceProxy $to, mixed $data, string|int $id)
    {
        parent::__construct($to, [$data], $id);
    }

}