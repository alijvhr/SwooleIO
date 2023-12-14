<?php

namespace SwooleIO;

use OpenSwoole\Constant;
use OpenSwoole\Core\Psr\Response as ServerResponse;
use OpenSwoole\Core\Psr\ServerRequest;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Server as OpenSwooleTCPServer;
use OpenSwoole\Table;
use OpenSwoole\WebSocket\Frame;
use OpenSwoole\Websocket\Server as OpenSwooleServer;
use Psr\Http\Server\MiddlewareInterface;
use SwooleIO\EngineIO\EngineIOMiddleware;
use SwooleIO\EngineIO\InvalidPacketException;
use SwooleIO\Lib\Singleton;
use SwooleIO\Psr\Middleware\NotFoundHandler;
use SwooleIO\Psr\QueueRequestHandler;
use SwooleIO\SocketIO\Packet;

class Server extends Singleton
{

    protected static string $serverID;

    protected OpenSwooleServer $server;
    protected array $transports = ['polling', 'websocket'];
    protected string $path;
    protected Table $SIDs;
    protected Table $FDs;

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
        $this->SIDs->set($sid, ['user' => +$user, 'time' => time(), 'fd' => 0]);
    }

    public function isUpgraded(string $sid): bool
    {
        $fd = $this->SIDs->get($sid, 'fd');
        return boolval($fd);
    }

    public function registerMiddleware(MiddlewareInterface $middleware)
    {
        $this->httpStackHandler->add(new EngineIOMiddleware($this->path));
        return $this;
    }

    public function start(string $host = '0.0.0.0', int $port = 80, string $path = '/socket.io'): bool
    {
        $this->path = $path;
        $this->server = new OpenSwooleServer($host, $port, OpenSwooleTCPServer::POOL_MODE);
        foreach ($this->listeners as $listener) {
            $this->server->addlistener(...$listener);
        }
        $this->httpStackHandler->add(new EngineIOMiddleware($this->path));
        $this->setEvents();
        $this->createTables();
        return $this->server->start();
    }

    public function addListener(string $host, int $port, int $sockType)
    {
        return $this->listeners[] = [$host, $port, $sockType];
    }

    protected function setEvents()
    {
        $this->server->on('Open', [$this, 'onOpen']);
        $this->server->on('Message', [$this, 'onMessage']);
        $this->server->on('Close', [$this, 'onClose']);
        $this->server->on('Request', [$this, 'onRequest']);
        $this->server->on('ManagerStart', [$this, 'onManagerStart']);
    }

    protected function createTables()
    {
        // Session Tables
        $this->SIDs = new Table(1e4);
        $this->SIDs->column('user', Table::TYPE_INT, 8);
        $this->SIDs->column('time', Table::TYPE_INT, 8);
        $this->SIDs->column('fd', Table::TYPE_INT, 8);
        $this->SIDs->create();

        // FileDescriptor table
        $this->FDs = new Table(1e4);
        $this->FDs->column('sid', Table::TYPE_STRING, 64);
        $this->FDs->column('user', Table::TYPE_INT, 8);
        $this->FDs->column('time', Table::TYPE_INT, 8);
        $this->FDs->create();

    }

    public function onOpen(OpenSwooleServer $server, Request $request)
    {
        $sid = $request->get['sid'];
        $user = $this->SIDs->get($sid, 'user');
        if (isset($user) && preg_match("#^$this->path#", $request->server['request_uri'])) {
            $this->FDs->set($request->fd, ['sid' => $sid, 'user' => $user, 'time' => time()]);
            $this->SIDs->set($sid, ['user' => $user, 'time' => time(), 'fd' => $request->fd]);
            echo "server: handshake success with fd{$request->fd}\n";
        } else
            $this->server->disconnect($request->fd);
    }

    public function onMessage(OpenSwooleServer $server, Frame $frame)
    {
        try {
            $packet = Packet::parse($frame->data);
        } catch (InvalidPacketException $exception) {
            return;
        }
        switch ($packet->getEngineType(true)) {
            case 2:
                $server->push($frame->fd, EngineIO\Packet::create('pong', $packet->getPayload())->encode());
                break;
            case 5:
//                $server->push($frame->fd, SocketIO\Packet::create('connect')->encode());
//                $session = $this->FDs->get($frame->fd);
                break;
        }
    }

    public function onClose(OpenSwooleServer $server, $fd)
    {
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
}