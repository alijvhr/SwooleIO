<?php

namespace SwooleIO\Hooks\Request;

use Error;
use Swoole\ExitException;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Server;
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
use SwooleIO\Psr\Response as PsrResponse;
use SwooleIO\Psr\ServerRequest;
use SwooleIO\SocketIO\Packet;
use Throwable;

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
        if (str_starts_with($request->server['request_uri'], $this->io->path())) {
            $this->SocketIO($request, $response);
        } else {
            ob_start();
            foreach (['post', 'get', 'files', 'cookie', 'shared'] as $field) {
                co_set("_$field", $request->$field ?? []);
            }
            try {
                $serverRequest = ServerRequest::from($request);
                co_set('request', $serverRequest);
                $serverResponse = &co_set('response', new PsrResponse(''));
                $serverResponse = $this->handler->handle($serverRequest);
            } catch (ExitException|Error|Throwable $e) {
//                io()->log->error($e);
                if (!$e instanceof ExitException || $e->getStatus() !== 0) {
                    io()->log->error("Exit: {$e->getMessage()} in {$e->getFile()}({$e->getLine()}).\n{$e->getTraceAsString()}");
                }
                $serverResponse = co_get('response');
            }
            if (!isset($serverResponse)) {
                $response->end();
            } else {
                $serverResponse->getBody()->write((string)ob_get_clean());
                PsrResponse::emit($response, $serverResponse);
            }
        }
    }

    protected function SocketIO(Request $request, Response $response): bool
    {
        $sid = &$request->get['sid'];
        $cors = io()->cors();
        if ($cors) {
//            $response->header('access-control-allow-origin', $cors);
//            $response->header('access-control-allow-methods', 'GET, POST');
        }
        if ($request->get['transport'] === 'polling' && $sid) {
            $connection = Connection::recover($sid);
            if (isset($connection)) {
                if ($request->getMethod() === 'POST') {
                    $connection->receive(Packet::from($request->getContent()));
                    $response->write('ok');
                    return $response->end();
                }

                if ($connection->transport() !== Transport::polling || $connection->is(ConnectionStatus::upgrading, ConnectionStatus::upgraded)) {
                    return $response->end(EioPacket::create(EioPacketType::noop)->encode());
                }

                $connection->writable = $response->fd;
                $response->detach();
                return $connection->flush();
            }

            $response->status(400, 'Bad Request');
            return $response->end('{"code":1,"message":"Session ID unknown"}');
        }
        Connection::create($sid = $this->io->generateSid())->save(true)->request($request);
        return $response->end(EioPacket::create(EioPacketType::open, ['sid' => $sid, 'upgrades' => array_slice($this->io->transports, 1), 'maxPayload' => 1000000, 'pingInterval' => Connection::$pingInterval, 'pingTimeout' => Connection::$pingTimeout])->encode());
    }

}