<?php

namespace SwooleIO\IO;

use RuntimeException;
use Swoole\Event as SwEvent;
use Swoole\Server as SwServer;
use SwooleIO\Lib\ProcessID;
use SwooleIO\Process\Control;
use SwooleIO\Service\ServiceProcess;
use SwooleIO\Time\TimeManager;
use SwooleIO\VO\Event;

trait Server
{

    public protected(set) bool $reloading = false;

    public function listen(string $host, int $port, int $sockType): self
    {
        $this->endpoints[] = [$host, $port, $sockType];
        return $this;
    }

//    public function dispatch_func(SwServer $server, int $fd, int $type, ?string $data = null): int
//    {
//        $base = $fd % self::$cpus;
//        if (!in_array($type, [0, 4, 5])) {
//            return $base;
//        }
//        if ($worker = $this->table('fd')?->get(crc32($fd), 'worker')) {
//            return $worker;
//        }
//        if (preg_match('/[?&]sid=([^&\s]++)/i', $data ?? '', $match)) {
//            $worker = $this->table('sid')?->get($match[1], 'worker');
//        }
//        return $worker ?? $base;
//    }

    public function start(): bool
    {
        $server = $this->server;
//        if (isset($default))
//            $this->endpoints[] = $default;
//        else {
        foreach ($this->endpoints as $endpoint) {
            $this->server->addlistener(...$endpoint);
        }
//        }
        $this->defaultHooks($server);
        $server->on('Start', $this->onStart(...));
        $server->on('beforeShutdown', $this->onStop(...));
        $server->on('shutdown', $this->onShutdown(...));
//        $this->on('managerStart', fn() => TimeManager::end());
        foreach ($this->services as $service) {
            if ($service instanceof ServiceProcess) {
                $this->server->addProcess($service);
            }
        }
        $server->addProcess(new Control());
//        $server->on('reload', $this->reloaded(...));
        $this->dispatch(new Event('load'));
        $this->of('/');
        $this->started = true;
//        $server->on('WorkerError', fn() => $server->shutdown());
        return $this->server->start();
    }

    public function stop(): void
    {
        $this->log->info('shutting down');
        $this->server->shutdown();
    }

    public function id(?string $server = null, ?string $service = null, string|int|null $worker = null): ProcessID
    {
        if (!isset($this->pid)) {
            $worker ??= $this->server->worker_id;
            $service ??= $worker >= 0 ? 'worker' : 'manager';
            $server ??= 'SwooleIO';
            $this->pid = new ProcessID($server, $service, $worker);
        } else {
            if (isset($service)) {
                $this->pid->service = $service;
            }
            if (isset($worker)) {
                $this->pid->worker = $worker;
            }
        }
        swoole_set_process_name($this->pid);
        return $this->pid;
    }


    protected function restart(bool $full = true): void
    {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        if ($full) {
            $this->server->shutdown();
        } else {
            $this->server->reload();
        }
        $this->reloading = false;
    }

    public function reload(bool $restart = false, float $delay = 0): void
    {
        if (!$this->timers->active('reload')) {
            $this->reloading = true;
            $this->timers->after('reload', $delay, fn() => $this->restart($restart));
        }
    }


    protected function onShutdown(): void
    {
        $this->dispatch(new Event('shutdown'));
        if ($this->reloading) {
            usleep(1e5);
            $this->server->start();
        }
    }

    protected function onStop(): void
    {
        $this->started = false;
        TimeManager::end();
        SwEvent::Exit();
    }

    /**
     * @throws RuntimeException
     */
    protected function onStart(SwServer $server): void
    {
        foreach ($this->endpoints as $endpoint) {
            if (in_array($endpoint[2], [SWOOLE_UNIX_STREAM, SWOOLE_UNIX_DGRAM], true)) {
                $this->log->info("fix $endpoint[0]");
                $dir = dirname($endpoint[0]);
                if (@mkdir($dir, 0644, true) || is_dir($dir)) {
                    chmod($endpoint[0], 0777);
                } else {
                    throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
                }
            }
        }
        $this->dispatch(new Event('start'));
    }
}