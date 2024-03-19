<?php

namespace SwooleIO\EngineIO;

use OpenSwoole\Core\Psr\Response;
use OpenSwoole\Coroutine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SwooleIO\Constants\SocketStatus;
use SwooleIO\Constants\Transport;
use SwooleIO\EngineIO\Packet as EioPacket;
use SwooleIO\SocketIO\Packet;
use function SwooleIO\io;

class EngineIOMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $io = io();
        $uri = $request->getUri()->getPath();
        $method = $request->getMethod();
        $path = $io->path();
        if (str_starts_with($uri, $path)) {
            if ($request->getQueryParam('transport') == 'polling' && $sid = $request->getQueryParam('sid')) {
                $socket = Socket::recover($sid);
                if (!isset($socket)) $response = new Response('Required parameter missing', 400);
                elseif ($method == 'POST') {
                    $response = new Response('ok');
                    $socket->receive(Packet::from($request->getBody()));
                } else {
                    $timeout = time() + 5;
                    while (time() < $timeout) {
                        $isPolling = $socket->transport() == Transport::polling;
                        $buffer = $socket->drain();
                        if ($isPolling && $buffer) {
                            $response = new Response($buffer);
                            break;
                        } elseif (!$isPolling || $socket->is(SocketStatus::upgrading)) {
                            $response = new Response(EioPacket::create('noop')->encode());
                            break;
                        } else
                            Coroutine::usleep(100);
                    }
                    if (!isset($response))
                        $response = new Response('2');
                }
            } else {
                Socket::create($sid = $io->generateSid())->save(true);
                $response = new Response(EioPacket::create('open', ["sid" => $sid, "upgrades" => array_slice($io->getTransports(), 1), "pingInterval" => 10000, "pingTimeout" => 5000])->encode());
            }
            /** @var ResponseInterface */
            return $response->withHeader('sid', $sid);
        } else
            return $handler->handle($request);
    }
}
