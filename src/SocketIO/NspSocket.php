<?php

namespace SwooleIO\SocketIO;

class NspSocket
{
    public function __construct(protected Socket $socket)
    {
    }

}