<?php

namespace SwooleIO\EngineIO;

class Adapter
{

    public static function get(): self
    {
        return new static();
    }

}