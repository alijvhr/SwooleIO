<?php

namespace SwooleIO\EngineIO\Transport;

use SwooleIO\EngineIO\Packet;
use SwooleIO\Lib\Transport;

class WebSocket extends Transport
{

    public function doClose(callable $fn = null)
    {
        // TODO: Implement doClose() method.
    }

    public function supportsFraming(): bool
    {
        // TODO: Implement supportsFraming() method.
    }

    public function name(): string
    {
        // TODO: Implement name() method.
    }

    public function send(Packet ...$packets): void
    {
        // TODO: Implement send() method.
    }
}