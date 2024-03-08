<?php

namespace SwooleIO\SocketIO;

use OpenSwoole\Core\Psr\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SwooleIO\EngineIO\Packet as EioPacket;
use function SwooleIO\io;
use function SwooleIO\uuid;

class SocketIOMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $io = io();
        $uri = $request->getUri()->getPath();
        $method = $request->getMethod();
        $GET = $request->getQueryParams();
        $path = $io->path();
        if (str_starts_with($uri, $path)) {
            $sid = $GET['sid'] ?? base64_encode(substr(uuid(), 0, 19) . $io->getServerID());
            $socket = $io->socket($sid, $request);
            if ($socket->sid()) {
                if ($method == 'POST') {
                    $packet = Packet::from($request->getBody());
                    $io->of($packet->getNamespace())->receive($socket, $packet);
                    return new Response('ok');
                }
                return new Response($socket->flush()?: EioPacket::create('noop')->encode());
            }
            $socket->sid($sid);
            $packet = EioPacket::create('open', ["sid" => $sid, "upgrades" => $io->getTransports(), "pingInterval" => 25000, "pingTimeout" => 5000]);
            $response = new Response($packet->encode());
            /** @var ResponseInterface */
            return $response->withHeader('sid', $sid);
        } else
            return $handler->handle($request);
    }
}
