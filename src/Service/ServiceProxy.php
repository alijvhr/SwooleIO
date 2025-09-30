<?php

namespace SwooleIO\Service;

use SwooleIO\Service;
use function SwooleIO\io;

/**
 * @mixin Service
 * @property-read static $await
 * @property-read bool $response
 */
class ServiceProxy
{

    static protected array $cache = [];

    protected bool $await = false;

    public readonly bool $local;

    public function __construct(public readonly string $service, public string|int|null $id = null, public ?string $server = null, ?ServiceProcess $process = null)
    {
        $this->server = $server ?: io()->id()->server;
        $this->local = $this->server === io()->id()->server;
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
            $return = $this->await ? $async?->get() : null;
            $this->await = false;
            return $return;
        }

        if (is_a($this->service, Service::class, true)) {
            return is_null($this->id) ? $this->service::$name(...$arguments) : $this->service::get($this->id)?->$name(...$arguments);
        }

        return null;

    }

    public function __get(string $name)
    {
        switch ($name) {
            case  'await':
                $this->await = true;
                return $this;
            case  'response':
                return $this->await;
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