<?php

namespace SwooleIO\Lib;

abstract class Singleton {

    protected static $instances = [];

    private function __construct() {
        $this->init();
    }

    abstract function init();


    /**
     * @return static
     */
    public static function getInstance() {
        $cls = static::class;
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static();
        }

        return self::$instances[$cls];
    }

    private function __clone() {
    }
}