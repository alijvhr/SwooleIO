<?php

namespace SwooleIO\EngineIO;

use OpenSwoole\Server;
use OpenSwoole\Table;
use SwooleIO\Lib\Singleton;

class Tables extends Singleton
{

    protected Server $server;
    protected array $tables;


    public function init()
    {
        // Session Tables
        $this->tables['sid'] = new Table(1e4);
        $this->tables['sid']->column('user', Table::TYPE_INT, 8);
        $this->tables['sid']->column('time', Table::TYPE_INT, 8);
        $this->tables['sid']->column('fd', Table::TYPE_INT, 8);
        $this->tables['sid']->create();

        // FileDescriptor table
        $this->tables['fd'] = new Table(1e4);
        $this->tables['fd']->column('sid', Table::TYPE_STRING, 64);
        $this->tables['fd']->column('user', Table::TYPE_INT, 8);
        $this->tables['fd']->column('time', Table::TYPE_INT, 8);
        $this->tables['fd']->create();

    }

    public function from(string $name): ?Table
    {
        return $this->tables[$name] ?? null;
    }

}