<?php

namespace SwooleIO\Lib;

use phpDocumentor\Reflection\Types\True_;

abstract class Singleton
{

    protected static $instances = [];

    private function __construct(bool $run)
    {
        if ($run)
            $this->init();
    }

    abstract function init();


    /**
     * @param bool $run
     * @return static
     */
    public static function instance(bool $run = true): static
    {
        $cls = static::class;
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static($run);
        }

        return self::$instances[$cls];
    }

    private function __clone()
    {
    }
}