<?php

namespace SwooleIO\Lib;

use OpenSwoole\Process\Pool;
use OpenSwoole\Server;

class PassiveProcess
{

    /** @var class-string<Process> */
    protected string $class;

    /** @var 'worker'|'manager' */
    protected string $type;
    protected Process $process;
    protected mixed $data;

    /**
     * @param Server|Pool $container
     * @param string $type
     * @param class-string<Process> $class
     * @param mixed|null $data
     * @return PassiveProcess
     */
    public static function hook(Server|Pool $container, string $type, string $class, mixed $data = null): self
    {
        $passiveProcess = new self();
        $passiveProcess->class = $class;
        $passiveProcess->type = $type;
        $passiveProcess->data = $data;
        $container->on("{$type}Start", [$passiveProcess, 'start']);
        $container->on("{$type}Stop", [$passiveProcess, 'stop']);
        return $passiveProcess;
    }

    public function start(Server|Pool $container, int $workerID = null): void
    {
        if (is_subclass_of($this->class, Process::class))
            $this->process = $this->class::start($container, $this->data, $workerID);
    }

    public function stop(Server|Pool $container, int $workerID = null): void
    {
        if (isset($this->process))
            $this->process->exit();

    }

}