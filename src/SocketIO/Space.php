<?php

namespace SwooleIO\SocketIO;

use SwooleIO\EngineIO\Adapter;

class Space
{

    public string $name;
    public Adapter $adapter;
    private array $middlewares = [];
    /**
     * @var mixed
     */
    private $server;

    final private function __construct(string $name, string $server)
    {
        $this->server = $server;
        $this->name = $name;
        $this->_initAdapter();
    }

    private function _initAdapter()
    {
        $adapterClass = $this->server->adapter();
        $this->adapter = new $adapterClass($this);
    }

    public static function get(string $name, ?Server $server)
    {
        if (!isset(self::$routes[$name]))
            self::$routes[$name] = new static($name, $server);
        return self::$routes[$name];
    }

    public function use(Middleware $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function to($room)
    {
        return new BroadcastOperator($this)->to($room);
    }

    public function in($room)
    {
        return new BroadcastOperator($this->adapter)->in($room);
    }

    public function except($room)
    {
        return new BroadcastOperator($this->adapter)->except($room);
    }

    function _add($client, $auth, $fn)
    {
        // ...
    }

    public function write(...$args)
    {
        return $this->send(...$args);
    }

    public function send(...$args)
    {
        $this->emit("message", ...$args);
        return $this;
    }

    public function emit($ev, ...$args)
    {
        return new BroadcastOperator($this->adapter)->emit($ev, ...$args);
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
        return new BroadcastOperator($this->adapter)->fetchSockets();
    }

    public function compress($compress)
    {
        return new BroadcastOperator($this->adapter)->compress($compress);
    }

    public function volatile()
    {
        return new BroadcastOperator($this->adapter)->volatile();
    }

    public function local()
    {
        return new BroadcastOperator($this->adapter)->local();
    }

    public function timeout($timeout)
    {
        return new BroadcastOperator($this->adapter)->timeout($timeout);
    }

    public function disconnectSockets($close = false)
    {
        return new BroadcastOperator($this->adapter)->disconnectSockets($close);
    }

    /**
     * Executes the middleware for an incoming client.
     *
     * @param socket - the socket that will get added
     * @param fn - last fn call in the middleware
     * @private
     */
    private function run($socket, $fn)
    {
        $fns = $this->middlewares;
        if (empty($fns)) return $fn(null);

        $run = function ($i) use ($socket, $fns, &$run, $fn) {
            $fns[$i]($socket, function ($err) use ($i, $run, $socket, $fns, $fn) {
                if ($err) return $fn($err);
                if (!isset($fns[$i + 1])) return $fn(null);
                $run($i + 1);
            });
        };

        return $run(0);
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