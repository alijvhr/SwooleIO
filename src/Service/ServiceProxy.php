<?php

namespace SwooleIO\Service;

use SwooleIO\Service;

/**
 * @mixin Service
 */
class ServiceProxy
{

    protected static array $cache = [];

    /** @var static $await */
    public self $await {
        get {
            $this->return = true;
            return $this;
        }
    }
    public protected(set) bool $return = false;

    public bool $local {
        get => $this->server === io()->id()->server;
    }

    public function __construct(public readonly string $service, public string|int|null $id = null, public ?string $server = null, ?ServiceProcess $process = null)
    {
        $this->server = $server ?: io()->id()->server;
        if (isset($process)) {
            self::$cache[$this->service] = $process;
        }
    }

    public function __call(string $name, array $arguments)
    {
//        if (!$this->local) {
//            $client = new Socket(AF_UNIX, SOCK_STREAM, 0);
//            var_dump($client);
//            if ($client->connect($this->server, $this->port, 1)) {
//                $client->send(new Call($this, $name, $arguments, $this->id));
//            } else {
//                io()->log->error(new ConnectionError("Unable to connect to service: $client->errMsg($client->errCode)"));
//            }
//            $client->close();
//            return null;
//        }
        $service = self::$cache[$this->service] ?? io()->services->get($this->service, false);
        if ($service instanceof ServiceProcess) {
            self::$cache[$this->service] = $service;
            $async = $service->call($this, $name, $arguments);
            $return = $this->return ? $async?->get() : null;
            $this->return = false;
            return $return;
        }

        if (is_a($service, Service::class, true)) {
            return is_null($this->id) ? $service::$name(...$arguments) : $service::get($this->id)?->$name(...$arguments);
        }

        return null;

    }

    public static function for(string $alias, int|string|null $id = null, ?string $server = null): static
    {
        return new static($alias, $id, $server);
    }

    public static function from(Service $service): static
    {
        return new static($service::name, $service->id, io()->id()->server);
    }

}