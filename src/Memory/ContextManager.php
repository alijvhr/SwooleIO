<?php

namespace SwooleIO\Memory;

use OpenSwoole\Coroutine;

class ContextManager
{
    /**
     * @template T
     * @param string $key
     * @param T $value
     * @return T
     */
    public static function set(string $key, mixed $value): mixed
    {
        Coroutine::getContext(Coroutine::getCid())[$key] = $value;
        return $value;
    }

    /**
     * @template T
     * @param string $key
     * @param T $default
     * @return T|mixed
     */
    public static function &get(string $key, mixed $default = null): mixed
    {
        $cid = Coroutine::getCid();
        do {
            if (isset(Coroutine::getContext($cid)[$key])) {
                return Coroutine::getContext($cid)[$key];
            }
            $cid = Coroutine::getPcid($cid);
        } while ($cid !== -1);
        return $default;
    }
}