<?php

namespace SwooleIO\IO;

use SwooleIO\Service;
use SwooleIO\Service\ServiceProcess;
use SwooleIO\Service\ServiceProxy;
use TypeError;

class ServiceManager
{

    /**
     * @param string $alias
     * @return ServiceProxy|ServiceProcess|string|null
     */

    public function service(string $alias): ServiceProxy|ServiceProcess|string|null
    {
        if (!isset($this->services[$alias])) {
            var_dump(array_keys($this->services));
            foreach ($this->services as $service) {
                $name = $service instanceof ServiceProcess || $service instanceof ServiceProxy ? $service->service : (is_a($service, Service::class, true) ? $service : '');
                if ($name && str_ends_with($name, $alias)) return $service;
            }
            return null;
        }
        return $this->services[$alias];
    }

    /**
     * @param ServiceProxy|ServiceProcess|class-string<Service> $service
     * @param string|null $alias
     * @param int|string|null $init
     * @param string|null $server
     * @return ServiceProxy
     * @throws TypeError
     */
    public function addService(mixed $service, ?string $alias = null, int|string|null $init = null, ?string $server = null): ServiceProxy
    {
        if (!isset($alias)) {
            if ($service instanceof ServiceProcess || $service instanceof ServiceProxy) {
                $alias = $service->service;
            } elseif (is_a($service, Service::class, true)) {
                $alias = $service::name;
            } else {
                throw new TypeError('Service should be a class name or an instance of ServiceProcess');
            }
        }
        $this->services[$alias] = $service;
        return $service instanceof ServiceProxy ? $service : new ServiceProxy($alias, $init, $server);
    }
}