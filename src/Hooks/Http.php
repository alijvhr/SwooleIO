<?php

namespace SwooleIO\Hooks;

use Error;
use Exception;
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
use SwooleIO\Memory\ContextManager;
use SwooleIO\Psr\Handler\NotFoundHandler;
use SwooleIO\Psr\Handler\QueueRequestHandler;
use SwooleIO\Psr\Handler\StackRequestHandler;
use SwooleIO\Psr\Response as PsrResponse;
use SwooleIO\Psr\ServerRequest;
use SwooleIO\SocketIO\Packet;
use function SwooleIO\io;

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
//        } elseif (str_starts_with($request->server['request_uri'], '/testjson')) {
//            $response->setHeader('Content-Type', 'application/json; charset=utf-8');
//            $response->end('{"code":0,"message":"ok"}');
        } else {
            ob_start(/*function (string $buffer) use ($response) {
                if ($buffer) {
                    \Sparrow\response()?->getBody()->write($buffer);
                }
            }*/);
            foreach (['post', 'get', 'files', 'cookie'] as $field)
                ContextManager::set($field, $request->$field);
            try {
                $serverRequest = ServerRequest::from($request);
                ContextManager::set('request', $serverRequest);
                ContextManager::set('response', new PsrResponse(''));
                $serverResponse = $this->handler->handle($serverRequest);
            } catch (ExitException|Error|Exception $e) {
                if ($e instanceof Error || method_exists($e, 'getStatus') && $e->getStatus() !== 0) {
                    io()->log->error("Exit: {$e->getMessage()} in {$e->getFile()}({$e->getLine()}).\n{$e->getTraceAsString()}");
                }
                $serverResponse = ContextManager::get('response');
            }
            if (!isset($serverResponse)) $response->end();
            else {
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
        if ($request->get['transport'] == 'polling' && $sid) {
            $connection = Connection::recover($sid);
            if (isset($connection)) {
                if ($request->getMethod() == 'POST') {
                    $connection->receive(Packet::from($request->getContent()));
                    $response->write('ok');
                    return $response->end();
                } elseif ($connection->transport() != Transport::polling || $connection->is(ConnectionStatus::upgrading, ConnectionStatus::upgraded)) {
                    return $response->end(EioPacket::create(EioPacketType::noop)->encode());
                } else {
                    $connection->writable = $response->fd;
                    $response->detach();
                    return $connection->flush();
                }
            } else {
                $response->status(400, 'Bad Request');
                return $response->end('{"code":1,"message":"Session ID unknown"}');
            }
        }
        Connection::create($sid = $this->io->generateSid())->save(true)->request($request);
        return $response->end(EioPacket::create(EioPacketType::open, ['sid' => $sid, 'upgrades' => array_slice($this->io->getTransports(), 1), 'maxPayload' => 1000000, 'pingInterval' => Connection::$pingInterval, 'pingTimeout' => Connection::$pingTimeout])->encode());
    }

}