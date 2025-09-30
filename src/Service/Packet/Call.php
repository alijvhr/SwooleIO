<?php

namespace SwooleIO\Service\Packet;

use AllowDynamicProperties;
use SwooleIO\Service\ServiceProxy;

#[AllowDynamicProperties]
class Call extends ServicePacket
{

    public function __construct(ServiceProxy $to, public readonly string $method, array $data = [], int|string|null $id = null)
    {
        parent::__construct($to, $data, $id);
    }

}