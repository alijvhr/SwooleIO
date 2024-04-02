<?php

namespace SwooleIO\Hooks;

use OpenSwoole\Core\Psr\Response as ServerResponse;
use OpenSwoole\Core\Psr\ServerRequest;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Server;
use SwooleIO\Constants\ConnectionStatus;
use SwooleIO\Constants\EioPacketType;
use SwooleIO\Constants\Transport;
use SwooleIO\EngineIO\Connection;
use SwooleIO\EngineIO\Packet as EioPacket;
use SwooleIO\IO;
use SwooleIO\Lib\Hook;
use SwooleIO\Psr\Handler\NotFoundHandler;
use SwooleIO\Psr\Handler\QueueRequestHandler;
use SwooleIO\Psr\Handler\StackRequestHandler;
use SwooleIO\SocketIO\Packet;

class Http extends Hook
{
    public StackRequestHandler $handler;
    protected IO $io;

    public function __construct(Server $target, bool $registerNow = false)
    {
        parent::__construct($target, $registerNow);
        $this->io = IO::instance();
        $this->handler = new QueueRequestHandler(new NotFoundHandler());
    }

    public function onRequest(Request $request, Response $response): void
    {
        $serverRequest = ServerRequest::from($request);
        $serverResponse = $this->handler->handle($serverRequest);
        if (str_starts_with($serverRequest->getUri()->getPath(), $this->io->path()))
            $this->SocketIO($request, $response);
        else
            ServerResponse::emit($response, $serverResponse);
    }

    protected function SocketIO(Request $request, Response $response): bool
    {
        $sid = &$request->get['sid'];
        if ($request->get['transport'] == 'polling' && $sid) {
            $connection = Connection::recover($sid);
            if (isset($connection)) {
                if ($request->getMethod() == 'POST') {
                    $connection->receive(Packet::from($request->getContent()));
                    return $response->write('ok');
                } else {
                    if ($connection->transport() != Transport::polling || $connection->is(ConnectionStatus::upgrading, ConnectionStatus::upgraded))
                        return $response->write(EioPacket::create(EioPacketType::noop)->encode());
                    else {
                        $connection->writable = $response->fd;
                        $response->detach();
                        return $connection->flush();
                    }
                }
            } else {
                $response->status(400, 'Bad Request');
                return $response->write('{"code":1,"message":"Session ID unknown"}');
            }

        } else {
            Connection::create($sid = $this->io->generateSid())->save(true)->request(ServerRequest::from($request));
            return $response->write(EioPacket::create(EioPacketType::open, ["sid" => $sid, "upgrades" => array_slice($this->io->getTransports(), 1), "maxPayload" => 1000000, "pingInterval" => Connection::$pingInterval, "pingTimeout" => Connection::$pingTimeout])->encode());
        }
    }

}