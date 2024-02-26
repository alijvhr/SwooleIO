<?php

namespace SwooleIO;

use OpenSwoole\Constant;
use OpenSwoole\Server as OpenSwooleTCPServer;
use OpenSwoole\Util;
use OpenSwoole\Websocket\Server as OpenSwooleServer;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use SwooleIO\EngineIO\EngineIOMiddleware;
use SwooleIO\IO\EventHandler;
use SwooleIO\IO\Http;
use SwooleIO\IO\PassiveProcess;
use SwooleIO\IO\Task;
use SwooleIO\Lib\Singleton;
use SwooleIO\Memory\DuplicateTableNameException;
use SwooleIO\Memory\Table;
use SwooleIO\Memory\TableContainer;
use SwooleIO\Psr\Event\ListenerProvider;
use SwooleIO\Psr\Middleware\NotFoundHandler;
use SwooleIO\Psr\QueueRequestHandler;
use SwooleIO\IO\WebSocket;
use SwooleIO\SocketIO\Route;
use Toolkit\Cli\Util\Clog;

class IO extends Singleton implements LoggerAwareInterface, LoggerInterface
{

    use LoggerAwareTrait;
    use LoggerTrait;

    protected static string $serverID;

    protected OpenSwooleServer $server;
    protected TableContainer $tables;
    protected EventHandler $evHandler;
    protected QueueRequestHandler $reqHandler;
    protected array $transports = ['polling', 'websocket'];

    protected string $path;
    protected array $listeners;

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
        $this->reqHandler = new QueueRequestHandler(new NotFoundHandler());
        $this->evHandler = new EventHandler();
        $this->tables = new TableContainer([
            'fd' => [['sid' => 'str', 'room' => 'list'], 5e4],
            'sid' => [['fd' => 'arr', 'room' => 'list'], 2e4],
            'room' => [['fd' => 'arr', 'sid' => 'list'], 2e3]
        ]);
    }

    public function dispatch(StoppableEventInterface $event): StoppableEventInterface
    {
        return $this->evHandler->dispatch($event);
    }

    public function on(string $event, callable $listener): ListenerProvider
    {
        return $this->evHandler->on($event, $listener);
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
        $this->reqHandler->add(new EngineIOMiddleware($this->path));
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

    public function start(string $host = '0.0.0.0', int $port = 80, string $path = '/socket.io'): bool
    {
        $this->path = $path;
        $this->server = $server = new OpenSwooleServer($host, $port, OpenSwooleTCPServer::POOL_MODE);
        $server->set(['task_worker_num' => Util::getCPUNum(), 'task_enable_coroutine' => true]);
        foreach ($this->listeners as $listener)
            $this->server->addlistener(...$listener);
        Http::register($server)->setHandler($this->reqHandler->add(new EngineIOMiddleware($this->path)));
        WebSocket::register($server);
        Task::register($server);
        PassiveProcess::hook($server, 'Manager', 'SwooleIO\Process\Manager', $this);
        PassiveProcess::hook($server, 'Worker', 'SwooleIO\Process\Worker', $this);
//        $this->server->defer([$this, 'afterStart']);
        return $this->server->start();
    }

    public function listen(string $host, int $port, int $sockType): self
    {
        $this->listeners[] = [$host, $port, $sockType];
        return $this;
    }

    public function of(string $namespace): Route
    {
        return Route::get($namespace);
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        Clog::log($level, interpolate($message, $context));
    }

    protected function afterStart(): void
    {
        foreach ($this->listeners as $listener) {
            if (in_array($listener[2], [Constant::UNIX_STREAM, Constant::UNIX_DGRAM]))
                chmod($listener[0], 0777);
        }
    }
}