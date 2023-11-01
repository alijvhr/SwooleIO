<?php

namespace SwooleIO\Lib;

class Builder
{

    public static function __callStatic($name, ...$arguments): ?self
    {
        if (method_exists(static::class, $name)) {
            $object = new static();
            return $object->$name(...$arguments);
        }
        return null;
    }
}