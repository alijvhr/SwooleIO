<?php

namespace SwooleIO\SocketIO;

use SwooleIO\Constants\SioPacketType;
use SwooleIO\Exceptions\ConnectionError;
use SwooleIO\Lib\EventHandler;
use function SwooleIO\io;

class Nsp
{
    use EventHandler;

    /** @var Nsp[] */
    protected static array $namespaces = [];
    public string $path;
    /**
     * @var callable[]
     */
    protected array $middlewares = [];

    final private function __construct(string $name)
    {
        $this->path = $name;
        $this->_initAdapter();
    }

    private function _initAdapter()
    {
        //TODO: return redis adapter
        io();
    }

    public static function get(string $name): self
    {
        if (!isset(self::$namespaces[$name]))
            self::$namespaces[$name] = new static($name);
        return self::$namespaces[$name];
    }

    public function use(callable $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function in($room): BroadcastOperator
    {
        return (new BroadcastOperator($this))->to($room);
    }

    public function to($room): BroadcastOperator
    {
        return (new BroadcastOperator($this))->to($room);
    }

    public function except($room): BroadcastOperator
    {
        return (new BroadcastOperator($this))->except($room);
    }

    function _add($client, $auth, $fn)
    {
        // ...
    }

    public function write(...$args): bool
    {
        return $this->send(...$args);
    }

    public function send(...$args): bool
    {
        return $this->emit("message", ...$args);
    }

    public function emit($ev, ...$args): bool
    {
        return (new BroadcastOperator($this))->emit($ev, ...$args);
    }

    public function serverSideEmit($ev, ...$args)
    {
        // ...
    }

    public function serverSideEmitWithAck($ev, ...$args)
    {
        // ...
    }

    public function fetchSockets()
    {
        return (new BroadcastOperator($this))->fetchSockets();
    }

    public function compress($compress)
    {
        return (new BroadcastOperator($this))->compress($compress);
    }

    public function volatile()
    {
        return (new BroadcastOperator($this))->volatile();
    }

    public function local()
    {
        return (new BroadcastOperator($this))->local();
    }

    public function timeout($timeout)
    {
        return (new BroadcastOperator($this))->timeout($timeout);
    }

    public function disconnectSockets($close = false)
    {
        (new BroadcastOperator($this))->disconnectSockets($close);
    }

    public function receive(Socket $socket, Packet $packet): void
    {
        go(function () use ($socket, $packet) {
            if ($packet->getSocketType(true) == 0) {
            }
        });
    }

    private function run(Socket $socket)
    {
        $fns = array_reverse($this->middlewares);
        $next = fn($i, $soc, $next) => $fns[$i]($soc, fn() => isset($fns[$i + 1]) ? $next($i + 1, $soc, $next) : null);
        return $next(0,$socket, $next);
    }

    public function connect(Socket $socket, Packet $packet): void
    {
        go(function (Socket $socket, Packet $packet) {
            try {
                $this->run($socket);
                $ev = new Event($socket, $packet);
                $ev->type = 'connection';
                $this->dispatch($ev);
                $socket->emitReserved(SioPacketType::connect, ['sid' => $socket->cid()]);
            }catch (ConnectionError $e){
                $socket->emitReserved(SioPacketType::connect_error, ['message' => $e->getMessage()]);
            }
        }, $socket, $packet);
    }

    private function _createSocket($client, $auth)
    {
        // ...
    }

    private function _doConnect($socket, $fn)
    {
        // ...
    }

    private function _remove($socket)
    {
        // ...
    }

    private function _onServerSideEmit($args)
    {
        // ...
    }
}