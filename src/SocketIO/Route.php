<?php

namespace SwooleIO\SocketIO;

use OpenSwoole\Table;
use Psr\Http\Server\RequestHandlerInterface;
use SwooleIO\EngineIO\Adapter;
use SwooleIO\IO;
use function SwooleIO\io;

class Route
{

    public string $path;
    public Adapter $adapter;
    protected array $middlewares = [];

    protected static array $routes = [];

    /**
     * @var callable[][]
     */
    protected array $listeners = [];

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
        if (!isset(self::$routes[$name]))
            self::$routes[$name] = new static($name);
        return self::$routes[$name];
    }

    public function use(RequestHandlerInterface $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function to($room): BroadcastOperator
    {
        return (new BroadcastOperator($this))->to($room);
    }

    public function in($room): BroadcastOperator
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
        return (new BroadcastOperator($this))->disconnectSockets($close);
    }

    public function receive(string $session, string $event, array $params, int $id)
    {
        $listeners = $this->listeners[$event]??[];
        foreach ($listeners as $listener)
            $listener($session);
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