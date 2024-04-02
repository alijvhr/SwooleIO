<?php

namespace SwooleIO\SocketIO;

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
class RemoteSocket implements SocketInterface
{

    public function __construct(protected string $sid, protected Transport $transport, protected string $workerId, protected string $nsp = '/', protected string|object $auth = '', protected ?int $fd = null)
    {

    }

    public static function from(Socket $socket): self
    {
        return new self($socket->sid(), $socket->transport(), $socket->workerId(), $socket->nsp(), $socket->auth(), $socket->fd());
    }

    public function __get(string $name)
    {
        return $this?->$name;
    }

    public function emit(string $event, mixed ...$data): bool
    {
        $io = io();
        $server = $io->server();
        $packet = Packet::create(SioPacketType::event, $event, ...$data)->setNamespace($this->nsp);
        return $this->transport == Transport::websocket && $server->isEstablished($this->fd) && $server->push($this->fd, $packet->encode()) || $io->serverSideEmit($this->workerId, ['send', $this->sid, $packet]);
    }

    public function sid(): string
    {
        return $this->sid;
    }

    public function auth(): array|object|string
    {
        return $this->auth;
    }

    public function transport(): Transport
    {
        return $this->transport;
    }

    public function workerId(): int
    {
        return $this->workerId;
    }

    public function nsp(): string
    {
        return $this->nsp;
    }

    public function fd(): ?int
    {
        return $this->fd;
    }
}