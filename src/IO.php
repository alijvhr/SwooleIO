<?php

namespace SwooleIO;

use DirectoryIterator;
use ErrorException;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Sparrow\Lib\Service;
use Sparrow\Lib\Service\ServiceProcess;
use Sparrow\Lib\Service\ServiceProxy;
use SplFileInfo;
use Swoole\Coroutine;
use Swoole\Event;
use Swoole\Process;
use Swoole\Runtime;
use Swoole\Server;
use Swoole\WebSocket\Server as WebsocketServer;
use SwooleIO\Exceptions\DuplicateTableNameException;
use SwooleIO\Hooks\Http;
use SwooleIO\Hooks\Task;
use SwooleIO\Hooks\UDP;
use SwooleIO\Hooks\WebSocket;
use SwooleIO\Lib\EventHandler;
use SwooleIO\Lib\PassiveProcess;
use SwooleIO\Lib\ProcessId;
use SwooleIO\Lib\SimpleEvent;
use SwooleIO\Lib\Singleton;
use SwooleIO\Memory\Table;
use SwooleIO\Memory\TableContainer;
use SwooleIO\Psr\Handler\StackRequestHandler;
use SwooleIO\Psr\Logger\FallbackLogger;
use SwooleIO\SocketIO\Nsp;
use SwooleIO\Time\TimeManager;
use SwooleIO\Time\Timer;
use Throwable;
use TypeError;

/**
 * @property-read LoggerInterface $log
 */
class IO extends Singleton implements LoggerAwareInterface
{

    use LoggerAwareTrait;
    use EventHandler;

    protected static string $serverID;
    protected static string $service;
    protected ProcessId $pid;
    protected static int $cpus;
    public readonly int $metrics;
    protected array $hotReloadPaths = [];
    protected bool $reloading = false;
    protected bool $started = false;
    protected TimeManager $timers;
    protected WebsocketServer $server;
    protected TableContainer $tables;
    protected array $transports = ['polling', 'websocket'];
    protected string $path;
    protected array $endpoints = [];
    protected StackRequestHandler $reqHandler;
    private string $cors = '';
    protected array $configs = [
        'service' => ['return' => ['timeout' => 0.1]],
    ];

    /** @var ServiceProcess[]|Service[] */
    protected array $services = [];

    /**
     * @param string $alias
     * @return null|ServiceProcess|class-string<Service>
     */
    public function service(string $alias): ServiceProxy|ServiceProcess|string|null
    {
        if (!isset($this->services[$alias])) {
            var_dump(array_keys($this->services));
            foreach ($this->services as $service) {
                $name = $service instanceof ServiceProcess || $service instanceof ServiceProxy ? $service->service : (is_a($service, Service::class, true) ? $service : '');
                if ($name && str_ends_with($name, $alias)) return $service;
            }
            return null;
        }
        return $this->services[$alias];
    }

    /**
     * @param ServiceProxy|ServiceProcess|class-string<Service> $service
     * @param string|null $alias
     * @param int|string|null $init
     * @param string|null $server
     * @return ServiceProxy
     */
    public function addService(mixed $service, ?string $alias = null, int|string|null $init = null, string $server = null): Service\ServiceProxy
    {
        if (!isset($alias)) {
            if ($service instanceof ServiceProcess || $service instanceof ServiceProxy) {
                $alias = $service->service;
            } elseif (is_a($service, Service::class, true)) {
                $alias = $service::name;
            } else {
                throw new TypeError('Service should be a class name or an instance of ServiceProcess');
            }
        }
        $this->services[$alias] = $service;
        return $service instanceof ServiceProxy ? $service : new ServiceProxy($alias, $init, $server);
    }

    public function getTransports(): array
    {
        return $this->transports;
    }

    public function middleware(MiddlewareInterface $middleware): static
    {
        $this->reqHandler->add($middleware);
        return $this;
    }

    public function path(string $path = null): string|self
    {
        if (!isset($path)) return $this->path;
        $this->path = $path;
        return $this;
    }

    public function config(string $name, mixed $default = null): mixed
    {
        $config = $this->configs;
        foreach (explode('.', $name) as $key) if (isset($config[$key])) $config = $config[$key]; else return $default;
        return $config;
    }

    public function configs(array $configs = []): array
    {
        return $this->configs = array_merge($this->configs, $configs);
    }

    public function dispatch_func(Server $server, int $fd, int $type, string $data = null): int
    {
        $base = $fd % self::$cpus;
        if (!in_array($type, [0, 4, 5])) return $base;
        if ($worker = $this->table('fd')->get(crc32($fd), 'worker'))
            return $worker;
        if (preg_match('/[?&]sid=([^&\s]++)/i', $data ?? '', $match))
            $worker = $this->table('sid')->get($match[1], 'worker');
        return $worker ?? $base;
    }

    public function table(string $name): ?Table
    {
        return $this->tables->get($name);
    }

    public function tables(): TableContainer
    {
        return $this->tables;
    }

    public function metrics(int $port = 9501): static
    {
        if (!isset($this->metrics)) {
            $this->metrics = $port;
            $this->on('load', function () use ($port) {
                $metrics = $this->server->listen('0.0.0.0', $port, SWOOLE_SOCK_TCP);
                $metrics->on('request', function ($request, $response) {
                    $response->header('Content-Type', 'text/plain');
                    $response->end(io()->server()->stats());
                });
            });
        }
        return $this;
    }

    public function listen(string $host, int $port, int $sockType): self
    {
        $this->endpoints[] = [$host, $port, $sockType];
        return $this;
    }

    public function server(): WebsocketServer
    {
        return $this->server;
    }

    public function start(): bool
    {
        $server = $this->server;
        if (isset($default))
            $this->endpoints[] = $default;
        else
            foreach ($this->endpoints as $endpoint)
                $this->server->addlistener(...$endpoint);
        $this->defaultHooks($server);
        $server->on('Start', function (Server $server) {
            foreach ($this->endpoints as $endpoint) {
                if (in_array($endpoint[2], [SWOOLE_UNIX_STREAM, SWOOLE_UNIX_DGRAM])) {
                    $this->log->info("fix $endpoint[0]");
                    $dir = dirname($endpoint[0]);
                    if (!is_dir($dir))
                        mkdir($dir, 0644, true);
                    chmod($endpoint[0], 0777);
                }
            }
            $this->dispatch(new SimpleEvent('start'));
        });
        $server->addProcess(new Process($this->controlProcess(...)));
//        $server->on('reload', $this->reloaded(...));
        $this->on('managerStart', fn() => TimeManager::end());
        foreach ($this->services as $service) {
            if ($service instanceof ServiceProcess) {
                $this->server->addProcess($service->process);
            }
        }
        $server->on('beforeShutdown', function () {
            $this->started = false;
            Coroutine::set(['hook_flags' => 0]);
            Coroutine::disableScheduler();
            TimeManager::end();
            Event::Exit();
        });
        $server->on('shutdown', function () {
            $this->dispatch(new SimpleEvent('shutdown'));
        });
        $this->dispatch(new SimpleEvent('load'));
        $this->of('/');
        $this->started = true;
//        $server->on('WorkerError', fn() => $server->shutdown());
        return $this->server->start();
    }

    protected function inotifyWatch($inotify, string $path): void
    {
        inotify_add_watch($inotify, $path, IN_MODIFY | IN_CREATE);
        if (is_dir($path)) {
            try {
                $dir = new DirectoryIterator($path);
                /** @var SplFileInfo $file */
                foreach ($dir as $file) {
                    $subdir = $file->getRealPath();
                    if ($file->isDir() && str_starts_with($subdir, "$path/")) {
                        $this->inotifyWatch($inotify, $subdir);
                    }
                }
            } catch (Throwable $e) {
            }
        }
    }

    protected function controlProcess(): void
    {
        if ($this->hotReloadPaths && function_exists('inotify_init')) {
            $inotify = inotify_init();
            foreach ($this->hotReloadPaths as $path) {
                $this->log->info("Adding $path to hot reload paths");
                $this->inotifyWatch($inotify, $path);
            }
            Event::add($inotify, function () use ($inotify) {
                $changes = inotify_read($inotify);
                if ($this->reloading) return;
                foreach ($changes as $change) {
                    if (str_ends_with($change['name'], '.php')) {
                        $this->log->info('File modification detected. reloading...');
                        $this->timers->after('reload', 1, function (Timer $timer) {
                            if (function_exists('opcache_reset'))
                                opcache_reset();
                            $this->reloading = false;
                            $this->server->reload();
                        });
                    }
                }
                $this->reloading = true;
            });
        }
        $this->timers->start();
        Event::wait();
    }

    public function of(string $namespace): Nsp
    {
        return Nsp::get($namespace);
    }

    public function hotReload(string $path): IO
    {
        if (file_exists($path))
            $this->hotReloadPaths[] = $path;
        return $this;
    }

    public function close(int $fd): bool
    {
        return $this->server->close($fd);
    }

    public function generateSid(): string
    {
        return base64_encode(substr(uuid(), 0, 19) . $this->getServerID());
    }

    public function __get(string $name)
    {
        return match ($name) {
            'log' => $this->logger
        };
    }

    public function getServerID(): string
    {
        return self::$serverID ?? '';
    }

    public function setServerID(string $id): static
    {
        if (!$this->started) {
            self::$serverID = $id;
            swoole_set_process_name($id);
        }
        return $this;
    }

    public function stop(): void
    {
        $this->log->info('shutting down');
        $this->server->shutdown();
    }

    public function defer(callable $fn): void
    {
        if ($this->started)
            swoole_event_defer($fn);
        else
            $this->on('start', fn() => swoole_event_defer($fn));
    }

    public function tick(float $interval, callable $fn, array $arguments = []): Timer
    {
        return $this->timers[] = Timer::tick($interval, $fn, $arguments, $this->started);
    }

    public function after(float $after, callable $fn, array $arguments = []): Timer
    {
        return $this->timers[] = Timer::after($after, $fn, $arguments, $this->started);
    }

    public function serverSideEmit(string $workerId, array $data): bool
    {
        return $this->server->sendMessage(serialize($data), $workerId);
    }

    public function id(?string $service = null, string|int|null $worker = null): ProcessId
    {
        if (!isset($this->pid)) {
            $worker ??= $this->server->worker_id;
            $service ??= $worker >= 0 ? 'worker' : 'manager';
            $this->pid = new ProcessId($this->getServerID(), $service, $worker);
        } else {
            if (isset($service))
                $this->pid->service = $service;
            if (isset($worker))
                $this->pid->worker = $worker;
        }
        swoole_set_process_name($this->pid);
        return $this->pid;
    }

    public function cors(string $fqdn = null): IO|string
    {
        if (!isset($fqdn))
            return $this->cors;
        $this->cors = $fqdn;
        return $this;
    }

    /**
     * @throws DuplicateTableNameException
     * @throws ErrorException
     */
    final protected function init(string $ID = null): void
    {
        $this->path = '/socket.io';
        $this->timers = new TimeManager();
        self::$serverID = $ID ?? substr(uuid(), -17);
        self::$cpus = swoole_container_cpu_num();
        $this->logger = new FallbackLogger();
        $this->tables = new TableContainer([
            'fd'   => [['sid' => 'str', 'worker' => 'int'], 1e4],
            'sid'  => [['pid' => 'str', 'fd' => 'int', 'cid' => 'list', 'sock' => 'object', 'transport' => 'int', 'worker' => 'int'], 1e4],
            'pid'  => [['sid' => 'str'], 1e4],
            'room' => [['namespace' => 'str', 'cid' => 'list'], 1e4],
            'cid'  => [['sid' => 'str', 'namespace' => 'str', 'rooms' => 'list'], 5e4],
            'nsp'  => [['cid' => 'list', 'rooms' => 'list'], 1e3],
        ]);
        $this->server = $server = new WebsocketServer('0.0.0.0', 0, SWOOLE_BASE, SWOOLE_SOCK_TCP);
        $server->set([
            'task_worker_num'             => 5,
            'worker_num'                  => min(self::$cpus, 20),
            'reactor_num'                 => min(self::$cpus, 20),
            //            'dispatch_func'           => $this->dispatch_func(...),
            'enable_preemptive_scheduler' => true,
            'open_http_protocol'          => true,
            'open_http2_protocol'         => true,
            'open_websocket_protocol'     => true,
            'task_enable_coroutine'       => true,
            'enable_coroutine'            => true,
            'send_yield'                  => true,
            'websocket_compression'       => true,
            'http_compression'            => true,
            'compression_min_length'      => 512,
            'backlog'                     => 512,
            'log_level'                   => SWOOLE_LOG_ERROR,
        ]);
        Runtime::enableCoroutine();
        Coroutine::set([
            'enable_deadlock_check'        => true,
            'deadlock_check_disable_trace' => false,
            'hook_flags'                   => SWOOLE_HOOK_ALL,
        ]);
    }

    /**
     * @param WebsocketServer $server
     * @return void
     */
    protected function defaultHooks(WebsocketServer $server): void
    {
        $this->reqHandler = Http::register($server)->handler;
        WebSocket::register($server);
        Task::register($server);
        UDP::register($server);
        PassiveProcess::hook($server, 'Manager', 'SwooleIO\Process\Manager');
        PassiveProcess::hook($server, 'Worker', 'SwooleIO\Process\Worker');
    }
}