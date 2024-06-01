<?php

namespace SwooleIO\Lib;

use phpDocumentor\Reflection\Types\True_;

abstract class Singleton
{

    protected static array $instances = [];

    private function __construct(bool $run, ...$args)
    {
        if ($run)
            $this->init(...$args);
    }

    abstract protected function init(...$args): void;


    /**
     * @param bool $run
     * @param mixed ...$args
     * @return static
     */
    public static function instance(bool $run = true, ...$args): static
    {
        $cls = static::class;
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static($run, ...$args);
        }

        return self::$instances[$cls];
    }

    private function __clone()
    {
    }
}