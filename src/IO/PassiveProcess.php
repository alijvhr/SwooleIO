<?php

namespace SwooleIO\IO;

use OpenSwoole\Process\Pool;
use OpenSwoole\Server;
use OpenSwoole\Server as OpenSwooleTCPServer;
use SwooleIO\Lib\Process;

class PassiveProcess
{

    /** @var class-string<Process> */
    protected string $class;

    /** @var 'worker'|'manager' */
    protected string $type;
    protected Process $process;
    protected mixed $data;

    /**
     * @param OpenSwooleTCPServer|Pool $container
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

    protected function start(Server|Pool $container, int $workerID): void
    {
        if (is_subclass_of($this->class, Process::class))
            $this->process = $this->class::start($container, $workerID, $this->data);
    }

    protected function stop(Server|Pool $container, int $workerID): void
    {
        if (isset($this->process))
            $this->process->exit();

    }

}