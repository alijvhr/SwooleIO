<?php

namespace SwooleIO\Lib;

abstract class Builder
{

    public static function __callStatic($name, $arguments): mixed
    {
        if (method_exists(static::class, $name)) {
            return new static()->$name(...$arguments);
        }
        return null;
    }
}