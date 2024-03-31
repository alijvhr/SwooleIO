<?php

namespace SwooleIO\SocketIO;

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

    public static function exists(string $name): bool
    {
        return isset(self::$namespaces[$name]);
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

    public function write(...$args): void
    {
        $this->send(...$args);
    }

    public function send(...$args): void
    {
        $this->emit("message", ...$args);
    }

    public function emit($ev, ...$args): void
    {
        (new BroadcastOperator($this))->emit($ev, ...$args);
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
        //TODO: Remote Socket needed
    }

    public function compress(bool $compress = true): BroadcastOperator
    {
        return (new BroadcastOperator($this))->compress($compress);
    }

    public function volatile(): BroadcastOperator
    {
        return (new BroadcastOperator($this))->volatile();
    }

    public function local(): BroadcastOperator
    {
        return (new BroadcastOperator($this))->local();
    }

    public function timeout($timeout): BroadcastOperator
    {
        return (new BroadcastOperator($this))->timeout($timeout);
    }

    public function disconnectSockets($close = false): bool
    {
        (new BroadcastOperator($this))->disconnectSockets($close);
    }

    /**
     * @throws ConnectionError
     */
    public function connect(Socket $socket, Packet $packet): void
    {
        $connected = false;
        $this->run($socket, function () use ($socket, $packet, &$connected) {
            $ev = new Event($socket, $packet);
            $ev->type = 'connection';
            $this->dispatch($ev);
            $connected = true;
        });
        if(!$connected)
            throw new ConnectionError('Something gone wrong!');
    }

    private function run(Socket $socket, callable $connect): void
    {
        if ($fns = array_reverse($this->middlewares)) {
            $next = fn($i, $soc, $next) => $fns[$i]($soc, fn() => isset($fns[$i + 1]) ? $next($i + 1, $soc, $next) : $connect);
            $next(0, $socket, $next);
        }
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