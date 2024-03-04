<?php

namespace SwooleIO\Hooks;

use OpenSwoole\Http\Request;
use OpenSwoole\WebSocket\Frame;
use OpenSwoole\WebSocket\Server as OpenSwooleServer;
use SwooleIO\EngineIO\InvalidPacketException;
use SwooleIO\EngineIO\Packet as EioPacket;
use SwooleIO\IO;
use SwooleIO\Lib\Hook;
use SwooleIO\SocketIO\Packet;

class WebSocket extends Hook
{

    protected IO $io;

    public function __construct(object $target, bool $registerNow = false)
    {
        parent::__construct($target, $registerNow);
        $this->io = IO::instance();
    }

    public function onOpen(OpenSwooleServer $server, Request $request): void
    {
        $io = $this->io;
        $path = $io->path();
        $sid = $request->get['sid'];
        $user = $io->table('sid')->exists($sid);
        if ($user && str_starts_with($path, $request->server['request_uri'])) {
            $io->table('fd')->set($request->fd, ['sid' => $sid]);
            $io->table('sid')->set($sid, ['user' => $user, 'time' => time(), 'fd' => $request->fd]);
            echo "server: handshake success with fd{$request->fd}\n";
        } else
            $server->disconnect($request->fd);
    }

    /**
     * @param OpenSwooleServer $server
     * @param Frame $frame
     * @return void
     * @throws InvalidPacketException
     */
    public function onMessage(OpenSwooleServer $server, Frame $frame): void
    {
        $packet = Packet::parse($frame->data);
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

    public function onClose(OpenSwooleServer $server, $fd): void
    {
    }

    public function all(): array
    {
        return ['Open', 'Close', 'Message'];
    }
}