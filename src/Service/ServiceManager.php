<?php

namespace SwooleIO\Service;

use ArrayAccess;
use Countable;
use Iterator;
use SwooleIO\Service;
use TypeError;

class ServiceManager implements Iterator, Countable, ArrayAccess
{

    /** @var class-string<Service>[]|ServiceProcess[] */
    protected array $services;

    /**
     * @param string $alias
     * @param bool $proxy
     * @return class-string<Service>|ServiceProcess|ServiceProxy|null
     */

    public function get(string $alias, bool $proxy = true): null|string|ServiceProcess|ServiceProxy
    {
        if (!isset($this->services[$alias])) {
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $alias = array_find_key(
                $this->services,
                static fn($item) => strtolower(is_string($item) ? $item : $item->service) === strtolower($alias)
            );
            if (is_null($alias)) {
                return null;
            }
        }
        if ($proxy) {
            return new ServiceProxy($alias, process: $this->services[$alias]);
        }
        return $this->services[$alias];
    }

    /**
     * @param class-string<Service>|ServiceProcess $service
     * @param string|null $alias
     * @param int|string|null $init
     * @return ServiceProxy
     */
    public function add(string|ServiceProcess $service, ?string $alias = null, int|string|null $init = null): ServiceProxy
    {
        $this->services[$alias] = $service;
        return new ServiceProxy($alias, $init);
    }

    public function current(): ServiceProcess
    {
        return current($this->services);
    }

    public function next(): void
    {
        next($this->services);
    }

    public function key(): string
    {
        return key($this->services);
    }

    public function valid(): bool
    {
        return key($this->services) !== null;
    }

    public function rewind(): void
    {
        reset($this->services);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->get($offset) !== null;
    }

    public function offsetGet(mixed $offset): ServiceProcess|Service
    {
        return $this->get($offset);
    }

    /**
     * @throws TypeError
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($value instanceof ServiceProcess || is_a($value, Service::class, true)) {
            $this->services[$offset] = $value;
        } else {
            throw new TypeError('Object should be a ServiceProcess Or a Service class name');
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->services[$offset]);
    }

    public function count(): int
    {
        return count($this->services);
    }
}