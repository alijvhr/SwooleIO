<?php

namespace SwooleIO;

use OpenSwoole\Constant;
use OpenSwoole\Server as OpenSwooleTCPServer;
use OpenSwoole\Util;
use OpenSwoole\Websocket\Server as OpenSwooleServer;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use SwooleIO\Hooks\Http;
use SwooleIO\Hooks\Task;
use SwooleIO\Hooks\WebSocket;
use SwooleIO\IO\EventHandler;
use SwooleIO\IO\PassiveProcess;
use SwooleIO\Lib\Singleton;
use SwooleIO\Memory\DuplicateTableNameException;
use SwooleIO\Memory\Table;
use SwooleIO\Memory\TableContainer;
use SwooleIO\Psr\Logger\FallbackLogger;
use SwooleIO\Psr\Middleware\NotFoundHandler;
use SwooleIO\Psr\QueueRequestHandler;
use SwooleIO\SocketIO\Route;
use SwooleIO\SocketIO\SocketIOMiddleware;

class IO extends Singleton implements LoggerAwareInterface
{

    use LoggerAwareTrait;

    protected static string $serverID;

    protected OpenSwooleServer $server;
    protected TableContainer $tables;
    protected EventHandler $evHandler;
    protected QueueRequestHandler $reqHandler;
    protected array $transports = ['polling', 'websocket'];

    protected string $path;
    protected array $endpoints;

    public function getServerID(): string
    {
        return self::$serverID ?? '';
    }

    /**
     * @throws DuplicateTableNameException
     */
    public function init(): void
    {
        self::$serverID = substr(uuid(), -17);
        $this->logger = new FallbackLogger();
        $this->reqHandler = new QueueRequestHandler(new NotFoundHandler());
        $this->reqHandler->add(new SocketIOMiddleware());
        $this->evHandler = new EventHandler();
        $this->tables = new TableContainer([
            'fd' => [['sid' => 'str', 'room' => 'list'], 5e4],
            'sid' => [['fd' => 'arr', 'room' => 'list'], 2e4],
            'room' => [['fd' => 'arr', 'sid' => 'list'], 2e3]
        ]);
    }

    public function event(): EventHandler
    {
        return $this->evHandler;
    }

    public function getTransports(): array
    {
        return $this->transports;
    }

    public function reportWorker(): void
    {
        $wid = $this->server->getWorkerId();
        echo "worker: $wid\n";
    }

    public function table(string $name): ?Table
    {
        return $this->tables->get($name);
    }

    public function middleware(MiddlewareInterface $middleware): static
    {
        $this->reqHandler->add($middleware);
        return $this;
    }

    public function server(): OpenSwooleServer
    {
        return $this->server;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function start(string $path = '/socket.io'): bool
    {
        $this->path = $path;
        [$host, $port, $sockType] = array_pop($this->endpoints)?? ['0.0.0.0', 80, OpenSwooleTCPServer::POOL_MODE];
        $this->server = $server = new OpenSwooleServer($host, $port, $sockType);
        $server->set(['task_worker_num' => Util::getCPUNum(), 'task_enable_coroutine' => true, 'enable_coroutine' => true, 'send_yield' => true]);
        foreach ($this->endpoints as $endpoint)
            $this->server->addlistener(...$endpoint);
        $this->defaultHooks($server);
        $this->server->after(50, [$this, 'afterStart']);
        return $this->server->start();
    }

    /**
     * @param OpenSwooleServer $server
     * @return void
     */
    protected function defaultHooks(OpenSwooleServer $server): void
    {
        Http::register($server)->setHandler($this->reqHandler);
        WebSocket::register($server);
        Task::register($server);
        PassiveProcess::hook($server, 'Manager', 'SwooleIO\Process\Manager', $this);
        PassiveProcess::hook($server, 'Worker', 'SwooleIO\Process\Worker', $this);
    }

    public function listen(string $host, int $port, int $sockType): self
    {
        $this->endpoints[] = [$host, $port, $sockType];
        return $this;
    }

    public function of(string $namespace): Route
    {
        return Route::get($namespace);
    }

    public function log(): ?LoggerInterface
    {
        return $this->logger;
    }

    protected function afterStart(): void
    {
        foreach ($this->endpoints as $endpoint) {
            if (in_array($endpoint[2], [Constant::UNIX_STREAM, Constant::UNIX_DGRAM]))
                chmod($endpoint[0], 0777);
        }
    }
}