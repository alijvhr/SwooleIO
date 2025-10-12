<?php

namespace SwooleIO;

use ErrorException;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Runtime;
use Swoole\WebSocket\Server as WebsocketServer;
use SwooleIO\Exceptions\DuplicateTableNameException;
use SwooleIO\Exceptions\TableDoesNotExistsException;
use SwooleIO\Hooks\Process;
use SwooleIO\Hooks\Request\Http;
use SwooleIO\Hooks\Request\UDP;
use SwooleIO\Hooks\Request\WebSocket;
use SwooleIO\Hooks\Task;
use SwooleIO\IO\Configurable;
use SwooleIO\IO\Server;
use SwooleIO\IO\Timers;
use SwooleIO\Lib\EventHandler;
use SwooleIO\Lib\ProcessID;
use SwooleIO\Lib\Singleton;
use SwooleIO\Memory\Table;
use SwooleIO\Memory\TableContainer;
use SwooleIO\Psr\Handler\StackRequestHandler;
use SwooleIO\Psr\Logger\FallbackLogger;
use SwooleIO\Service\ServiceManager;
use SwooleIO\SocketIO\Nsp;
use SwooleIO\Time\TimeManager;

/**
 * @property-read LoggerInterface $log
 */
class IO extends Singleton implements LoggerAwareInterface
{

    use LoggerAwareTrait;
    use EventHandler;
    use Configurable;
    use Timers;
    use Server;

    protected static string $serverID;
    protected static string $service;
    protected ProcessID $pid;
    protected static int $cpus;
    public readonly int $metrics;

    public readonly TimeManager $timers;
    public readonly WebsocketServer $server;
    public readonly TableContainer $tables;
    public readonly ServiceManager $services;
    public readonly Watcher $watcher;
    protected bool $started = false;
    public array $transports = ['polling', 'websocket'];
    protected string $path;
    protected array $endpoints = [];
    protected StackRequestHandler $reqHandler;
    private string $cors = '';

    public function middleware(MiddlewareInterface $middleware): static
    {
        $this->reqHandler->add($middleware);
        return $this;
    }

    public function path(string|null $path = null): string|self
    {
        if (!isset($path)) return $this->path;
        $this->path = $path;
        return $this;
    }

    /**
     * @throws TableDoesNotExistsException
     */
    public function table(string $name): Table
    {
        $table = $this->tables->get($name);
        if (!isset($table)) {
            throw new TableDoesNotExistsException($name);
        }
        return $this->tables->get($name);
    }

    public function metrics(int $port = 9501): static
    {
        if (!isset($this->metrics)) {
            $this->metrics = $port;
            $this->on('load', function () use ($port) {
                $metrics = $this->server->listen('0.0.0.0', $port, SWOOLE_SOCK_TCP);
                $metrics->on('request', function ($request, $response) {
                    $response->header('Content-Type', 'text/plain');
                    $response->end(io()->server->stats());
                });
            });
        }
        return $this;
    }


    public function of(string $namespace): Nsp
    {
        return Nsp::get($namespace);
    }

    public function close(int $fd): bool
    {
        return $this->server->close($fd);
    }

    public function generateSid(): string
    {
        return base64_encode(substr(uuid(), 0, 19) . $this->id());
    }

    public function __get(string $name)
    {
        return match ($name) {
            'log' => $this->logger
        };
    }

    public function cors(string|null $fqdn = null): IO|string
    {
        if (!isset($fqdn))
            return $this->cors;
        $this->cors = $fqdn;
        return $this;
    }

    public function serverSideEmit(string $workerId, array $data): bool
    {
        return $this->server->sendMessage(serialize($data), $workerId);
    }

    /**
     * @throws ErrorException
     */
    public function watch(string ...$path): static
    {
        foreach ($path as $p) {
            $this->watcher->add($p);
            if (!file_exists($p)) {
                throw new ErrorException("Path $p not found");
            }
        }
        return $this;
    }

    /**
     * @throws DuplicateTableNameException
     * @throws ErrorException
     */
    final protected function init(string|null $ID = null): void
    {
        self::$serverID = $ID ?? substr(uuid(), -17);
        self::$cpus = swoole_container_cpu_num();
        $this->timers = new TimeManager();
        $this->watcher = new Watcher();
        $this->services = new ServiceManager();
        $this->logger = new FallbackLogger();
        $this->tables = new TableContainer([
            'fd'   => [['sid' => 'str', 'worker' => 'int'], 1e4],
            'sid'  => [['pid' => 'str', 'fd' => 'int', 'cid' => 'list', 'sock' => 'object', 'transport' => 'int', 'worker' => 'int'], 1e4],
            'pid'  => [['sid' => 'str'], 1e4],
            'room' => [['namespace' => 'str', 'cid' => 'list'], 1e4],
            'cid'  => [['sid' => 'str', 'namespace' => 'str', 'rooms' => 'list'], 5e4],
            'nsp'  => [['cid' => 'list', 'rooms' => 'list'], 1e3],
        ]);
        $this->path = '/socket.io';
        $this->server = new WebsocketServer('0.0.0.0', 0, SWOOLE_BASE, SWOOLE_SOCK_TCP);
        $this->server->set([
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
        Process\Manager::register($server);
        Process\Worker::register($server);
    }
}