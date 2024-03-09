<?php

namespace SwooleIO\SocketIO;

use Psr\Http\Server\RequestHandlerInterface;
use SwooleIO\EngineIO\Adapter;
use SwooleIO\Lib\EventHandler;
use function SwooleIO\io;

class Nsp
{
    use EventHandler;

    protected static array $routes = [];
    public string $path;
    public Adapter $adapter;
    protected array $middlewares = [];
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

    public function on(string $event, callable $callback): self
    {
        if (isset($this->listeners[$event]))
            $this->listeners[$event][] = $callback;
        else
            $this->listeners[$event] = [$callback];
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
        io()->server()->defer(fn() => $this->dispatch($socket, $packet));
    }

    private function dispatch(Socket $socket, Packet $packet): void
    {
        $io = io();
        switch ($packet->getSocketType(true)) {
            case 0:
                $socket->push(Packet::create('connect', ['sid' => $io->generateSid()]));
                break;
            case 2:
                $io->of($packet->getNamespace())->receive($socket, $packet);
        }
        $listeners = $this->listeners[$packet->getEvent()] ?? [];
        foreach ($listeners as $listener)
            $listener($socket, $packet);
    }

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