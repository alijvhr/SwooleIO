<?php

namespace SwooleIO\Service\Packet;

use SwooleIO\Service\ServiceProxy;

class Exception extends ServicePacket
{

    public function __construct(ServiceProxy $to, \Throwable $exception, string|int $id)
    {
        parent::__construct($to, [$exception], $id);
    }

}