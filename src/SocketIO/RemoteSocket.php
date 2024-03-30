<?php

namespace SwooleIO\SocketIO;

use SwooleIO\Constants\ConnectionStatus;
use SwooleIO\Constants\SioPacketType;
use SwooleIO\Constants\Transport;
use function SwooleIO\io;

/**
 * @property-read $sid
 * @property-read $workerId
 * @property-read $nsp
 * @property-read $auth
 * @property-read $fd
 * @property-read $transport
 */
class RemoteSocket
{

    public function __construct(protected string $sid, protected Transport $transport, protected string $workerId, protected string $nsp = '/', protected string|object $auth = '', protected ?int $fd = null)
    {

    }

    public static function from(Socket $socket): self
    {
        $conn = $socket->connection();
        return new self($conn->sid(), $conn->transport(), io()->server()->getWorkerId(), $socket->nsp(), $socket->auth(), $conn->is(ConnectionStatus::upgraded)? $conn->fd(): null);
    }

    public function __get(string $name)
    {
        return $this?->$name;
    }

    public function emit(string $event, mixed ...$data): bool
    {
        $io = io();
        $packet = Packet::create(SioPacketType::event, $event, ...$data)->setNamespace($this->nsp);
        return $io->server()->push($this->fd, $packet->encode()) || $io->serverSideEmit($this->workerId, ['send', $this->sid, $packet]);
    }

}