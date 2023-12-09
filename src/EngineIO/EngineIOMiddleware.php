<?php

namespace SwooleIO\EngineIO;

use OpenSwoole\Core\Psr\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SwooleIO\EngineIO\Packet as EioPacket;
use SwooleIO\SocketIO\Packet as SioPacket;
use SwooleIO\Server;
use function SwooleIO\swooleio;
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
        $server = swooleio();
        $uri = $request->getUri();
        if (preg_match("/^$this->path/", $uri)) {
            $GET = $request->getQueryParams();
            if (isset($GET['sid'])) {
                return new Response(SioPacket::create('connect')->encode());
            }
            $sid = base64_encode(substr(uuid(), 0, 19) . $server->getServerID());
            $packet = EioPacket::create('open', ["sid" => $sid, "upgrades" => $server->getTransports(), "pingInterval" => 25000, "pingTimeout" => 5000]);
            $response = new Response($packet->encode());
            swooleio()->newSid($sid, ['user' => $request->getAttribute('uid', 0), 'time' => time()]);
            return $response->withHeader('sid', $sid);
        } else
            return $handler->handle($request);
    }
}
