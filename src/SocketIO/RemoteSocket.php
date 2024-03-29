<?php

namespace SwooleIO\SocketIO;

use SwooleIO\Constants\SioPacketType;
use function SwooleIO\io;

/**
 * @property-read $sid
 * @property-read $workerId
 * @property-read $nsp
 * @property-read $auth
 */
class RemoteSocket
{

    public function __construct(protected string $sid, protected string $workerId, protected string $nsp = '/', protected string|object $auth = '')
    {

    }

    public static function from(Socket $socket): self
    {
        return new self($socket->connection()->sid(), io()->server()->getWorkerId(), $socket->nsp(), $socket->auth());
    }

    public function __get(string $name)
    {
        return $this?->$name;
    }

    public function emit(string $event, mixed ...$data): bool
    {
        $packet = Packet::create(SioPacketType::event, $event, ...$data)->setNamespace($this->nsp);
        return io()->serverSideEmit($this->workerId, ['send', $this->sid, $packet]);
    }

}