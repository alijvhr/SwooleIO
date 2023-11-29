<?php

namespace SwooleIO;

use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Websocket\Server;
use Psr\Http\Server\MiddlewareInterface;
use SwooleIO\EngineIO\EngineIOMiddleware;
use SwooleIO\Psr\Middleware\NotFoundHandler;
use SwooleIO\Psr\QueueRequestHandler;

class SwooleIO
{

    protected static string $serverID;
    /**
     * @var mixed
     */
    protected Server $server;
    protected string $path;

    protected QueueRequestHandler $httpStackHandler;

    public function __construct(string $host = '0.0.0.0', int $port = 80, string $path = '/socket.io')
    {
        self::$serverID = substr(self::UUID(), -17);
        $this->server = new Server($host, $port);
        $this->path = $path;
        $this->setEvents();
        $this->httpStackHandler = new QueueRequestHandler(new NotFoundHandler());
        $this->httpStackHandler->add(new EngineIOMiddleware($this->path));
        $this->server->setHandler($this->httpStackHandler);
        $this->reportWorker();
    }

    public static function UUID(): string
    {
        $out = bin2hex(random_bytes(18));

        $out[8] = "-";
        $out[13] = "-";
        $out[18] = "-";
        $out[23] = "-";

        $out[14] = "4";

        $out[19] = ["8", "9", "a", "b"][random_int(0, 3)];

        return $out;
    }

    protected function setEvents()
    {
        $this->server->on('Open', [$this, 'onOpen']);
        $this->server->on('Message', [$this, 'onMessage']);
        $this->server->on('Close', [$this, 'onClose']);
        $this->server->on('Request', [$this, 'onRequest']);
    }

    public function reportWorker()
    {
        $wid = $this->server->getWorkerId();
        echo "worker: $wid\n";
    }

    public static function getServerID(): string
    {
        return self::$serverID ?? '';
    }

    public function registerMiddleware(MiddlewareInterface $middleware)
    {
        $this->httpStackHandler->add(new EngineIOMiddleware($this->path));
        return $this;
    }

    public function server()
    {
        return $this->server;
    }

    public function onOpen(Server $server, Request $request)
    {
        $server->push($request->fd, "this is server hello");
        if (strpos($this->path, $request->server['request_uri']) === 0) {
            $server->after(2000, function () use ($request) {
                $this->server->disconnect($request->fd);
            });
        } else
            echo "server: handshake success with fd{$request->fd}\n";
    }

    public function onMessage(Server $server, $frame)
    {
        echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
        $server->push($frame->fd, "this is server");
    }

    public function onClose(Server $server, $fd)
    {
        echo "client {$fd} closed\n";
    }

    public function onRequest(Request $request, Response $response)
    {
//        $this->reportWorker();
//        if (strpos($this->path, $request->server['request_uri']) === 0) {
//            $response->end('fuckyou');
//        } else {
//            $response->end('fuckyou2');
//        }
    }


}