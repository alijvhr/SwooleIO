<?php

namespace SwooleIO\Memory;

use RuntimeException;
use Swoole\Coroutine;

class ContextManager
{
    /**
     * @template T
     * @param string $key
     * @param T $value
     * @return T
     */
    public static function &set(string $key, mixed $value): mixed
    {
        Coroutine::getContext(Coroutine::getCid())[$key] = &$value;
        return $value;
    }

    /**
     * @template T
     * @param string $key
     * @param T $default
     * @return T|mixed
     * @throws RuntimeException
     */
    public static function &find(string $key): mixed
    {
        $cid = Coroutine::getCid();
        do {
            if (isset(Coroutine::getContext($cid)[$key])) {
                return Coroutine::getContext($cid)[$key];
            }
            $cid = Coroutine::getPcid($cid);
        } while ($cid > 0);
        throw new \RuntimeException('Context not found in Coroutine');
    }

    public static function &get(string $key, bool $init = true): mixed
    {
        try {
            $var = &static::find($key);
        } catch (RuntimeException $e) {
            if ($init) {
                $var = &static::set($key, null);
            } else {
                $var = null;
            }
        }
        return $var;
    }
}