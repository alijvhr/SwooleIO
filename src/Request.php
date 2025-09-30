<?php

namespace SwooleIO;

use Swoole\Coroutine;
use SwooleIO\Psr\ServerRequest;

class Request extends ServerRequest
{
    public static function get(): ServerRequest
    {
        return Coroutine::getContext()['req'];
    }

    public static function set(ServerRequest $request): void
    {
        Coroutine::getContext()['req'] = $request;
    }

}