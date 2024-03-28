<?php

namespace SwooleIO;

use OpenSwoole\Constant;
use OpenSwoole\Server;
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
use SwooleIO\Lib\PassiveProcess;
use SwooleIO\Lib\Singleton;
use SwooleIO\Memory\Table;
use SwooleIO\Memory\TableContainer;
use SwooleIO\Psr\Handler\StackRequestHandler;
use SwooleIO\Psr\Logger\FallbackLogger;
use SwooleIO\SocketIO\Nsp;

class IO extends Singleton implements LoggerAwareInterface
{

    use LoggerAwareTrait;

    protected static string $serverID;
    protected static int $cpus;

    protected WebsocketServer $server;
    protected TableContainer $tables;
    protected array $transports = ['polling', 'websocket'];

    protected string $path;
    protected array $endpoints = [];
    protected StackRequestHandler $reqHandler;

    /**
     * @throws DuplicateTableNameException
     */
    public function init(): void
    {
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

    public function server(): WebsocketServer
    {
        return $this->server;
    }

    public function path(): string
    {
        return $this->path;
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

    public function start(string $path = '/socket.io'): bool
    {
        $this->path = $path;
        if (!$this->endpoints)
            $default = ['0.0.0.0', 80, Constant::SOCK_TCP];
        [$host, $port, $sockType] = $default ?? reset($this->endpoints);
        $this->server = $server = new WebsocketServer($host, $port, Server::POOL_MODE, $sockType);
        $server->set([
            'task_worker_num' => self::$cpus,
            'worker_num' => self::$cpus,
            'dispatch_func' => [$this, 'dispatch_func'],
            'open_http_protocol' => true,
            'open_http2_protocol' => true,
            'open_websocket_protocol' => true,
            'task_enable_coroutine' => true,
            'enable_coroutine' => true,
            'send_yield' => true,
            'websocket_compression' => true
        ]);
        if (isset($default))
            $this->endpoints[] = $default;
        else
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

    public function on(string $event, callable $callback): Psr\Event\ListenerProvider
    {
        return Nsp::get('/')->on($event, $callback);
    }

    public function listen(string $host, int $port, int $sockType): self
    {
        $this->endpoints[] = [$host, $port, $sockType];
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

    public function onStart(): void
    {
        foreach ($this->endpoints as $endpoint) {
            if (in_array($endpoint[2], [Constant::UNIX_STREAM, Constant::UNIX_DGRAM])) {
                $this->log()->info("fix $endpoint[0]");
                $dir = dirname($endpoint[0]);
                if (!is_dir($dir))
                    mkdir($dir, 0644, true);
                chmod($endpoint[0], 0777);
            }
        }
    }

    public function log(): ?LoggerInterface
    {
        return $this->logger;
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
        $this->log()->info("shutting down");
        $this->server->shutdown();
    }

    public function serverSideEmit(string $workerId, array $data): bool
    {
        return $this->server->sendMessage($workerId, serialize($data));
    }
}