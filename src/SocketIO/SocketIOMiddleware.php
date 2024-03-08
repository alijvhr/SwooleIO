<?php

namespace SwooleIO\SocketIO;

use OpenSwoole\Core\Psr\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SwooleIO\EngineIO\Packet as EioPacket;
use SwooleIO\IO\Socket;
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
            $sid = $GET['sid'] ?? $io->generateSid();
            $socket = Socket::fetch($sid, $request);
            if ($socket->sid()) {
                if ($method == 'POST') {
                    $packet = Packet::from($request->getBody());
                    $io->receive($socket, $packet);
                    return new Response('ok');
                }
                $buffer = $socket->flush();
                return new Response($socket->isConnected() || !$buffer ? EioPacket::create('noop')->encode() : $buffer);
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
