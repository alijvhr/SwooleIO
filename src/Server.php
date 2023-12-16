<?php

namespace SwooleIO;

use OpenSwoole\Constant;
use OpenSwoole\Core\Psr\Response as ServerResponse;
use OpenSwoole\Core\Psr\ServerRequest;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Server as OpenSwooleTCPServer;
use OpenSwoole\Table;
use OpenSwoole\Websocket\Server as OpenSwooleServer;
use Psr\Http\Server\MiddlewareInterface;
use SwooleIO\EngineIO\EngineIOMiddleware;
use SwooleIO\EngineIO\Tables;
use SwooleIO\EngineIO\WebSocket;
use SwooleIO\Lib\Singleton;
use SwooleIO\Psr\Middleware\NotFoundHandler;
use SwooleIO\Psr\QueueRequestHandler;
use SwooleIO\SocketIO\Space;

class Server extends Singleton
{

    protected static string $serverID;

    protected OpenSwooleServer $server;

    protected WebSocket $websocket;
    protected Tables $tables;
    protected array $transports = ['polling', 'websocket'];
    protected string $path;

    protected QueueRequestHandler $httpStackHandler;
    protected array $listeners;

    public function getServerID(): string
    {
        return self::$serverID ?? '';
    }

    public function init()
    {
        self::$serverID = substr(uuid(), -17);
        $this->httpStackHandler = new QueueRequestHandler(new NotFoundHandler());
    }

    public function getTransports(): array
    {
        return $this->transports;
    }

    public function setTransports(array $transports)
    {
        $this->transports = $transports;
    }

    public function reportWorker()
    {
        $wid = $this->server->getWorkerId();
        echo "worker: $wid\n";
    }

    public function newSid(string $sid, int $user)
    {
        $this->tables->from('sid')->set($sid, ['user' => +$user, 'time' => time(), 'fd' => 0]);
    }

    public function isUpgraded(string $sid): bool
    {
        $fd = $this->table('sid')->get($sid, 'fd');
        return boolval($fd);
    }

    public function middleware(MiddlewareInterface $middleware)
    {
        $this->httpStackHandler->add(new EngineIOMiddleware($this->path));
        return $this;
    }

    public function server(): OpenSwooleServer
    {
        return $this->server;
    }

    public function tables(): Tables
    {
        return $this->tables;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function table($name): ?Table
    {
        return $this->tables->from($name);
    }

    public function start(string $host = '0.0.0.0', int $port = 80, string $path = '/socket.io'): bool
    {
        $this->path = $path;
        $this->server = new OpenSwooleServer($host, $port, OpenSwooleTCPServer::POOL_MODE);
        foreach ($this->listeners as $listener) {
            $this->server->addlistener(...$listener);
        }
        $this->websocket = WebSocket::instance();
        $this->tables = Tables::instance();
        $this->httpStackHandler->add(new EngineIOMiddleware($this->path));
        $this->server->on('ManagerStart', [$this, 'onManagerStart']);
        return $this->server->start();
    }

    public function listen(string $host, int $port, int $sockType): self
    {
        $this->listeners[] = [$host, $port, $sockType];
        return $this;
    }

    public function onManagerStart(OpenSwooleServer $server)
    {
        foreach ($this->listeners as $listener) {
            if (in_array($listener[2], [Constant::UNIX_STREAM, Constant::UNIX_DGRAM]))
                chmod($listener[0], 0777);
        }
    }

    public function onRequest(Request $request, Response $response)
    {
        $serverRequest = ServerRequest::from($request);
        $serverResponse = $this->httpStackHandler->handle($serverRequest);
        ServerResponse::emit($response, $serverResponse);
    }

    public function of(string $namespace): Space
    {
        return Space::get($namespace);
    }

}