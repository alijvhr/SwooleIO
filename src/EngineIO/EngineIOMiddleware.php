<?php

namespace SwooleIO\EngineIO;

use OpenSwoole\Core\Psr\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SwooleIO\EngineIO\Packet as EioPacket;
use SwooleIO\SocketIO\Packet as SioPacket;
use SwooleIO\IO;
use function SwooleIO\io;
use function SwooleIO\uuid;

class EngineIOMiddleware implements MiddlewareInterface
{

    protected string $path;

    public function __construct(string $SocketIOPath = '/socket.io')
    {
        $this->path = $SocketIOPath;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $server = io();
        $uri = $request->getUri();
        $method = $request->getMethod();
        $GET = $request->getQueryParams();
        if (preg_match("#^$this->path#", $uri)) {
            if (isset($GET['sid'])) {
                if ($method == 'post') {
                    $payload = $request->getBody();
                    MessageBroker::instance()->receive($payload, $GET['sid']);
                    return new Response('ok');
                }
                return new Response(MessageBroker::instance()->flush($GET['sid']));
            }
            $sid = base64_encode(substr(uuid(), 0, 19) . $server->getServerID());
            $packet = EioPacket::create('open', ["sid" => $sid, "upgrades" => $server->getTransports(), "pingInterval" => 25000, "pingTimeout" => 5000]);
            $response = new Response($packet->encode());
            io()->setSession($sid, $request->getAttribute('uid', 0));
            return $response->withHeader('sid', $sid);
        } else
            return $handler->handle($request);
    }
}
