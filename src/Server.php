<?php

namespace SwooleIO;

use Swoole\Websocket\Server as SwooleServer;

class Server extends SwooleServer
{

    public function __construct($host, $port = null, $mode = null, $sock_type = null)
    {
        parent::__construct($host, $port, $mode, $sock_type);
        $this->on('Open', function(OpenSwoole\WebSocket\Server $server, $request)
        {
            echo "server: handshake success with fd{$request->fd}\n";
        });

        $this->on('Message', function(OpenSwoole\WebSocket\Server $server, $frame)
        {
            echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";

            $server->push($frame->fd, "this is server");
        });

        $this->on('Close', function(OpenSwoole\WebSocket\Server $server, $fd)
        {
            echo "client {$fd} closed\n";
        });

        // The Request event closure callback is passed the context of $server
        $this->on('Request', function(OpenSwoole\Http\Request $request, OpenSwoole\Http\Response $response)
        {
            /*
            * Loop through all the WebSocket connections to
            * send back a response to all clients. Broadcast
            * a message back to every WebSocket client.
            */
            foreach($this->connections as $fd)
            {
                // Validate a correct WebSocket connection otherwise a push may fail
                if($this->isEstablished($fd))
                {
                    $this->push($fd, $request->get['message']);
                }
            }
        });
    }
}