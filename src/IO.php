<?php

namespace SwooleIO;

use OpenSwoole\Constant;
use OpenSwoole\Server;
use OpenSwoole\Util;
use OpenSwoole\WebSocket\Server as WebsocketServer;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use SwooleIO\Hooks\Http;
use SwooleIO\Hooks\Task;
use SwooleIO\Hooks\WebSocket;
use SwooleIO\IO\EventHandler;
use SwooleIO\IO\PassiveProcess;
use SwooleIO\IO\Socket;
use SwooleIO\Lib\Singleton;
use SwooleIO\Memory\DuplicateTableNameException;
use SwooleIO\Memory\Table;
use SwooleIO\Memory\TableContainer;
use SwooleIO\Psr\Handler\StackRequestHandler;
use SwooleIO\Psr\Logger\FallbackLogger;
use SwooleIO\SocketIO\Route;

class IO extends Singleton implements LoggerAwareInterface
{

    use LoggerAwareTrait;

    protected static string $serverID;

    protected WebsocketServer $server;
    protected TableContainer $tables;
    protected EventHandler $evHandler;
    protected array $transports = ['polling', 'websocket'];

    protected string $path;
    protected array $endpoints = [];

    /** @var Socket[] */
    protected array $sockets;
    protected array $fd;
    protected StackRequestHandler $reqHandler;

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
        $this->evHandler = new EventHandler();
        $this->tables = new TableContainer([
            'fd' => [['pid' => 'str'], 1e5],
            'sid' => [['pid' => 'str', 'fd' => 'int'], 1e5],
            'pid' => [['fd' => 'int', 'sid' => 'str', 'room' => 'list', 'buffer' => 'text'], 1e5],
            'room' => [['pid' => 'list'], 1e4],
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

    public function middleware(MiddlewareInterface $middleware): static
    {
        $this->reqHandler->add($middleware);
        return $this;
    }

    public function add(Socket $socket): Socket
    {
        $this->table('pid')->set($socket->pid(), ['fd' => $socket->fd(), 'sid' => $socket->sid(), 'room' => [], 'buffer' => '']);
        if ($socket->sid())
            $this->table('sid')->set($socket->sid(), ['pid' => $socket->pid()]);
        if ($socket->fd())
        if ($socket->fd())
            $this->table('fd')->set($socket->fd(), ['pid' => $socket->pid()]);
        return $this->sockets[$socket->pid()] = $socket;
    }

    public function table(string $name): ?Table
    {
        return $this->tables->get($name);
    }

    public function server(): WebsocketServer
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
        [$host, $port, $sockType] = reset($this->endpoints) ?? $this->endpoints[] = ['0.0.0.0', 80, Constant::SOCK_TCP];
        $this->server = $server = new WebsocketServer($host, $port, Server::POOL_MODE, $sockType);
        $server->set(['task_worker_num' => Util::getCPUNum(), 'task_enable_coroutine' => true, 'enable_coroutine' => true, 'send_yield' => true, 'websocket_compression' => true]);
        foreach ($this->endpoints as $endpoint)
            $this->server->addlistener(...$endpoint);
        $this->defaultHooks($server);
        $this->server->after(50, [$this, 'onStart']);
        return $this->server->start();
    }

    /**
     * @param WebsocketServer $server
     * @return void
     */
    protected function defaultHooks(WebsocketServer $server): void
    {
        $server->on('Start', [$this, 'onStart']);
        $this->reqHandler = Http::register($server)->handler;
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

    public function socket(string $pid, RequestInterface $request): Socket
    {
        return $this->recover($pid, $request) ?? $this->add(new Socket($request, $pid));
    }

    public function recover(string $pid, RequestInterface $request): ?Socket
    {
        if (isset($this->sockets[$pid])) {
            $socket = $this->sockets[$pid];
            $socket->request = $request;
            return $socket;
        }
        $session = $this->table('pid')->get($pid);
        if (isset($session)) {
            $socket = new Socket($request, $pid);
            $socket->sid($session['sid']);
            $socket->fd($session['fd']);
        }
        return $socket ?? null;
    }

    public function close(int $fd): ?Socket
    {

        return $socket ?? null;
    }

    public function onStart(): void
    {
        foreach ($this->endpoints as $endpoint) {
            if (in_array($endpoint[2], [Constant::UNIX_STREAM, Constant::UNIX_DGRAM])) {
                $this->log()->info("fix $endpoint[0]");
                chmod($endpoint[0], 0777);
            }
        }
    }

    public function log(): ?LoggerInterface
    {
        return $this->logger;
    }
}