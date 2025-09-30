<?php

namespace SwooleIO\Service;

use ArrayAccess;
use Countable;
use Iterator;
use TypeError;

class ServiceManager implements Iterator, Countable, ArrayAccess
{

    /** @var ServiceProcess[] */
    protected array $services;

    /**
     * @param string $alias
     * @return ServiceProxy|null
     */

    public function get(string $alias, bool $proxy = true): null|ServiceProcess|ServiceProxy
    {
        if (!isset($this->services[$alias])) {
            $alias = array_find_key($this->services, static fn($service) => strtolower($service->service) === strtolower($alias));
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
     * @param ServiceProcess $service
     * @param string|null $alias
     * @param int|string|null $init
     * @return ServiceProxy
     */
    public function add(ServiceProcess $service, ?string $alias = null, int|string|null $init = null): ServiceProxy
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

    public function offsetGet(mixed $offset): ServiceProcess
    {
        return $this->get($offset);
    }

    /**
     * @throws TypeError
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($value instanceof ServiceProcess) {
            $this->services[$offset] = $value;
        } else {
            throw new TypeError('Object should be a ServiceProcess');
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