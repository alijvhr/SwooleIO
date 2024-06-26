<?php

namespace SwooleIO;

use OpenSwoole\Constant;
use OpenSwoole\Runtime;
use OpenSwoole\Server;
use OpenSwoole\Timer;
use OpenSwoole\Util;
use OpenSwoole\WebSocket\Server as WebsocketServer;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use SwooleIO\Exceptions\DuplicateTableNameException;
use SwooleIO\Hooks\Http;
use SwooleIO\Hooks\Task;
use SwooleIO\Hooks\WebSocket;
use SwooleIO\Lib\EventHandler;
use SwooleIO\Lib\PassiveProcess;
use SwooleIO\Lib\SimpleEvent;
use SwooleIO\Lib\Singleton;
use SwooleIO\Memory\Table;
use SwooleIO\Memory\TableContainer;
use SwooleIO\Psr\Handler\StackRequestHandler;
use SwooleIO\Psr\Logger\FallbackLogger;
use SwooleIO\SocketIO\Nsp;

class IO extends Singleton implements LoggerAwareInterface
{

    use LoggerAwareTrait;
    use EventHandler;

    protected static string $serverID;
    protected static int $cpus;
    public readonly int $metrics;
    protected WebsocketServer $server;
    protected TableContainer $tables;
    protected array $transports = ['polling', 'websocket'];
    protected string $path;
    protected array $endpoints = [];
    protected StackRequestHandler $reqHandler;
    private string $cors = '';

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

    public function dispatch_func(Server $server, int $fd, int $type, string $data = null): int
    {
        $base = $fd % self::$cpus;
        if (!in_array($type, [0, 4, 5])) return $base;
        if ($worker = $this->table('fd')->get($fd, 'worker'))
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
                $metrics = $this->server->listen('0.0.0.0', $port, Constant::SOCK_TCP);
                $metrics->on('request', function ($request, $response) {
                    $response->header('Content-Type', 'text/plain');
                    $response->end(io()->server()->stats(2));
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
                if (in_array($endpoint[2], [Constant::UNIX_STREAM, Constant::UNIX_DGRAM])) {
                    $this->log()->info("fix $endpoint[0]");
                    $dir = dirname($endpoint[0]);
                    if (!is_dir($dir))
                        mkdir($dir, 0644, true);
                    chmod($endpoint[0], 0777);
                }
            }
            $this->dispatch(new SimpleEvent('start'));
        });
        $server->on('shutdown', function () {
            Timer::clearAll();
            $this->dispatch(new SimpleEvent('shutdown'));
        });
        $this->dispatch(new SimpleEvent('load'));
        $this->of('/');
        $server->on('WorkerError', fn() => $server->shutdown());
        return $this->server->start();
    }

    public function log(): ?LoggerInterface
    {
        return $this->logger;
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
        return base64_encode(substr(uuid(), 0, 19) . $this->getServerID());
    }

    public function getServerID(): string
    {
        return self::$serverID ?? '';
    }

    public function stop(): void
    {
        $this->log()->info('shutting down');
        $this->server->shutdown();
    }

    public function defer(callable $fn): void
    {
        $this->server->defer($fn);
    }

    public function tick(int $ms, callable $fn): int
    {
        return $this->server->tick($ms, $fn);
    }

    public function after(int $ms, callable $fn): int
    {
        return $this->server->after($ms, $fn);
    }

    public function serverSideEmit(string $workerId, array $data): bool
    {
        return $this->server->sendMessage(serialize($data), $workerId);
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
     */
    final protected function init(...$args): void
    {
        $this->path = '/socket.io';
        self::$serverID = substr(uuid(), -17);
        self::$cpus = Util::getCPUNum();
        $this->logger = new FallbackLogger();
        $this->tables = new TableContainer([
            'fd' => [['sid' => 'str', 'worker' => 'int'], 1e4],
            'sid' => [['pid' => 'str', 'fd' => 'int', 'cid' => 'json', 'sock' => 'phps', 'transport' => 'int', 'worker' => 'int'], 1e4],
            'pid' => [['sid' => 'str'], 1e4],
            'room' => [['cid' => 'list'], 1e4],
            'cid' => [['conn' => 'phps'], 5e4],
            'nsp' => [['cid' => 'list'], 1e3],
        ]);
        $this->server = $server = new WebsocketServer('0.0.0.0', 0, Server::POOL_MODE, Constant::SOCK_TCP);
        $server->set([
            'task_worker_num' => self::$cpus,
            'worker_num' => self::$cpus,
            'dispatch_func' => $this->dispatch_func(...),
            'open_http_protocol' => true,
            'open_http2_protocol' => true,
            'open_websocket_protocol' => true,
            'task_enable_coroutine' => true,
            'enable_coroutine' => true,
            'send_yield' => true,
            'websocket_compression' => true,
            'http_compression' => true,
            'compression_min_length' => 512,
            'backlog' => 512,
            'log_level' => Constant::LOG_NONE,
        ]);
        Runtime::enableCoroutine();
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
        PassiveProcess::hook($server, 'Manager', 'SwooleIO\Process\Manager');
        PassiveProcess::hook($server, 'Worker', 'SwooleIO\Process\Worker');
    }
}