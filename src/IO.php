<?php

namespace SwooleIO;

use OpenSwoole\Server as OpenSwooleTCPServer;
use OpenSwoole\Websocket\Server as OpenSwooleServer;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\Http\Server\MiddlewareInterface;
use SwooleIO\EngineIO\EngineIOMiddleware;
use SwooleIO\IO\Http;
use SwooleIO\IO\PassiveProcess;
use SwooleIO\Lib\Singleton;
use SwooleIO\Memory\DuplicateTableNameException;
use SwooleIO\Memory\Table;
use SwooleIO\Memory\TableContainer;
use SwooleIO\Psr\Event\EventDispatcher;
use SwooleIO\Psr\Event\ListenerProvider;
use SwooleIO\Psr\Middleware\NotFoundHandler;
use SwooleIO\Psr\QueueRequestHandler;
use SwooleIO\IO\WebSocket;
use SwooleIO\SocketIO\Route;

class IO extends Singleton
{

    protected static string $serverID;

    protected OpenSwooleServer $server;

    protected WebSocket $wsServer;
    protected TableContainer $tables;
    protected array $transports = ['polling', 'websocket'];
    protected string $path;
    protected ListenerProvider $listener;
    protected QueueRequestHandler $handler;
    protected array $listeners;
    private EventDispatcher $dispatcher;

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
        $this->handler = new QueueRequestHandler(new NotFoundHandler());
        $this->listener = new ListenerProvider();
        $this->dispatcher = new EventDispatcher($this->listener);
        try {
            $this->tables->create('fd', ['sid' => 'str', 'room' => 'list'], 5e4);
            $this->tables->create('sid', ['fd' => 'arr', 'room' => 'list'], 2e4);
            $this->tables->create('room', ['fd' => 'arr', 'sid' => 'list'], 2e3);
        } catch (DuplicateTableNameException $e) {
            // TODO: add to log
            throw $e;
        }
    }

    public function dispatch(StoppableEventInterface $event): StoppableEventInterface
    {
        return $this->dispatcher->dispatch($event);
    }

    public function getTransports(): array
    {
        return $this->transports;
    }

    public function setTransports(array $transports): void
    {
        $this->transports = $transports;
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
        $this->handler->add(new EngineIOMiddleware($this->path));
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
        foreach ($this->listeners as $listener)
            $this->server->addlistener(...$listener);
        WebSocket::register($server);
        Http::register($server)->setHandler($this->handler->add(new EngineIOMiddleware($this->path)));
        PassiveProcess::hook($server, 'Manager', 'SwooleIO\Process\Manager', $this);
        PassiveProcess::hook($server, 'Worker', 'SwooleIO\Process\Worker', $this);
        return $this->server->start();
    }

    public function on(string $event, callable $listener): ListenerProvider
    {
        return $this->listener->addListener($event, $listener);
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

}