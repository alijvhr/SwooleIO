<?php

namespace SwooleIO;

use OpenSwoole\Core\Psr\Response as ServerResponse;
use OpenSwoole\Core\Psr\ServerRequest;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Table;
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
    protected array $transports = ['websocket'];
    protected string $path;
    protected Table $SIDs;
    protected Table $FDs;

    protected QueueRequestHandler $httpStackHandler;

    public function getServerID(): string
    {
        return self::$serverID ?? '';
    }

    public function init(string $host = '0.0.0.0', int $port = 80, string $path = '/socket.io')
    {
        self::$serverID = substr(uuid(), -17);
        $this->server = new OpenSwooleServer($host, $port);
        $this->path = $path;
        $this->httpStackHandler = new QueueRequestHandler(new NotFoundHandler());
        $this->httpStackHandler->add(new EngineIOMiddleware($this->path));

        $this->setEvents();
        $this->createTables();
    }

    protected function setEvents()
    {
        $this->server->on('Open', [$this, 'onOpen']);
        $this->server->on('Message', [$this, 'onMessage']);
        $this->server->on('Close', [$this, 'onClose']);
        $this->server->on('Request', [$this, 'onRequest']);
    }

    protected function createTables()
    {
        // Session Tables
        $this->SIDs = new Table(1e5);
        $this->SIDs->column('user', Table::TYPE_INT, 8);
        $this->SIDs->column('time', Table::TYPE_INT, 8);
        $this->SIDs->create();

        // FileDescriptor table
        $this->FDs = new Table(1e4);
        $this->FDs->column('sid', Table::TYPE_STRING, 64);
        $this->FDs->column('user', Table::TYPE_INT, 8);
        $this->FDs->column('time', Table::TYPE_INT, 8);
        $this->FDs->create();

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

    public function newSid($sid, $user)
    {
        $this->SIDs->set($sid, ['user' => $user, 'time' => time()]);
    }

    public function registerMiddleware(MiddlewareInterface $middleware)
    {
        $this->httpStackHandler->add(new EngineIOMiddleware($this->path));
        return $this;
    }

    public function addListener(string $host, int $port, int $sockType)
    {
        return $this->server->addlistener($host, $port, $sockType);
    }

    public function start(): bool
    {
        return $this->server->start();
    }

    public function onOpen(OpenSwooleServer $server, Request $request)
    {
        $sid = $request->get['sid'];
        $user = $this->SIDs->get($sid, 'user');
        if (isset($user) && preg_match("/^$this->path/", $request->server['request_uri'])) {
            $this->FDs->set($request->fd, ['sid' => $sid, 'user' => $user, 'time' => time()]);
            echo "server: handshake success with fd{$request->fd}\n";
        } else
            $this->server->disconnect($request->fd);
    }

    public function onMessage(OpenSwooleServer $server, $frame)
    {
        try {
            $packet = Packet::parse($frame);
        } catch (InvalidPacketException $exception) {
            return;
        }
        switch ($packet->getEngineType(true)) {
            case 2:
                $server->push($frame->fd, EngineIO\Packet::create('pong', $packet->getPayload()));
                break;
        }
    }

    public function onClose(OpenSwooleServer $server, $fd)
    {
        echo "client {$fd} closed\n";
    }

    public function onRequest(Request $request, Response $response)
    {
        $serverRequest = ServerRequest::from($request);
        $serverResponse = $this->httpStackHandler->handle($serverRequest);
        ServerResponse::emit($response, $serverResponse);
    }
}