<?php

namespace SwooleIO\EngineIO;

use OpenSwoole\Http\Request;
use OpenSwoole\WebSocket\Frame;
use OpenSwoole\WebSocket\Server as OpenSwooleServer;
use SwooleIO\EngineIO\Packet as EioPacket;
use SwooleIO\Lib\Singleton;
use OpenSwoole\WebSocket\Server;
use SwooleIO\SocketIO\Packet;
use function SwooleIO\io;

class WebSocket extends Singleton
{

    protected Server $server;

    function init()
    {
        $this->server = io()->server();
        $this->server->on('Open', [$this, 'onOpen']);
        $this->server->on('Message', [$this, 'onMessage']);
        $this->server->on('Close', [$this, 'onClose']);
        $this->server->on('Request', [$this, 'onRequest']);
    }

    public function onOpen(OpenSwooleServer $server, Request $request)
    {
        $swooleio = io();
        $path = $swooleio->path();
        $sid = $request->get['sid'];
        $user = $swooleio->table('sid')->get($sid, 'user');
        if (isset($user) && preg_match("#^$path#", $request->server['request_uri'])) {
            $swooleio->table('fd')->set($request->fd, ['sid' => $sid, 'user' => $user, 'time' => time()]);
            $swooleio->table('sid')->set($sid, ['user' => $user, 'time' => time(), 'fd' => $request->fd]);
            echo "server: handshake success with fd{$request->fd}\n";
        } else
            $this->server->disconnect($request->fd);
    }

    public function onMessage(OpenSwooleServer $server, Frame $frame)
    {
        try {
            $packet = Packet::parse($frame->data);
        } catch (InvalidPacketException $exception) {
            return;
        }
        switch ($packet->getEngineType(true)) {
            case 2:
                $server->push($frame->fd, EioPacket::create('pong', $packet->getPayload())->encode());
                break;
            case 5:
//                $server->push($frame->fd, SocketIO\Packet::create('connect')->encode());
//                $session = $this->FDs->get($frame->fd);
                break;
        }
    }

    public function onClose(OpenSwooleServer $server, $fd)
    {
    }
}