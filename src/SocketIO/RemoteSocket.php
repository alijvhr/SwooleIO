<?php

namespace SwooleIO\SocketIO;

use SwooleIO\Constants\SioPacketType;
use function SwooleIO\io;

class RemoteSocket
{

    public function __construct(public string $sid, public string $workerId, public string $nsp = '/', public string $auth = '')
    {

    }

    public function emit(string $event, mixed ...$data): bool
    {
        $packet = Packet::create(SioPacketType::event, $event, ...$data)->setNamespace($this->nsp);
        return io()->serverSideEmit($this->workerId, ['send', $this->sid, $packet]);
    }

}