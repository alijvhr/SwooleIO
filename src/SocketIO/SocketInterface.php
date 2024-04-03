<?php

namespace SwooleIO\SocketIO;

use SwooleIO\Constants\Transport;

interface SocketInterface
{
    public function emit(string $event, mixed ...$data): bool;

    public function sid(): string;
    public function auth(): array|object|string;
    public function transport(): Transport;
    public function workerId(): int;

    public function nsp(): string;

    public function fd(): ?int;

    public function ip();

    public function ua();

}