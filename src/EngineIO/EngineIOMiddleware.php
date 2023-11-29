<?php

namespace SwooleIO\EngineIO;

use OpenSwoole\Core\Psr\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SwooleIO\EngineIO\Packet as EioPacket;
use SwooleIO\SocketIO\Packet as SioPacket;
use SwooleIO\SwooleIO;

class EngineIOMiddleware implements MiddlewareInterface
{

    protected string $path;
    protected int $pathLen;

    public function __construct(string $SocketIOPath = '/socket.io')
    {
        $this->path = $SocketIOPath;
        $this->pathLen = strlen($SocketIOPath);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $path = substr($uri, 0, $this->pathLen);
        if ($path == $this->path) {
            $GET = $request->getQueryParams();
            if (isset($GET['sid'])) {
                $response = new Response($packet);
            }
            $sid = base64_encode(substr(SwooleIO::UUID(), 0, 19) . SwooleIO::getServerID());
            $packet = EioPacket::create('open', ["sid" => $sid, "upgrades" => ["websocket"], "pingInterval" => 25000, "pingTimeout" => 5000]);
            $packet->append(SioPacket::create('connect'));
            $response = new Response($packet);
            return $response->withHeader('x-a', '1234');
        } else
            return $handler->handle($request);
    }
}
