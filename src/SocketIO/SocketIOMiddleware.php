<?php

namespace SwooleIO\SocketIO;

use OpenSwoole\Core\Psr\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SwooleIO\EngineIO\MessageBroker;
use SwooleIO\EngineIO\Packet as EioPacket;
use function SwooleIO\io;
use function SwooleIO\uuid;

class SocketIOMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $io = io();
        $uri = $request->getUri();
        $method = $request->getMethod();
        $GET = $request->getQueryParams();
        $session = $GET['sid'] ?? '';
        $path = $io->path();
        if (str_starts_with($path, $uri)) {
            if ($session) {
                if ($method == 'post') {
                    $packet = Packet::from($request->getBody());
                    go(fn() => Route::get($packet->getNamespace())->receive($session, $packet));
                    return new Response('ok');
                }
                return new Response(MessageBroker::instance()->flush($GET['sid']));
            }
            $sid = base64_encode(substr(uuid(), 0, 19) . $io->getServerID());
            $packet = EioPacket::create('open', ["sid" => $sid, "upgrades" => $io->getTransports(), "pingInterval" => 25000, "pingTimeout" => 5000]);
            $response = new Response($packet->encode());
            return $response->withHeader('sid', $sid);
        } else
            return $handler->handle($request);
    }
}
