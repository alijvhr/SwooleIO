<?php

namespace SwooleIO\IO;

trait Configurable
{

    protected array $configs = [
        'service' => ['return' => ['timeout' => 0.1]],
    ];

    public function config(string $name, mixed $default = null): mixed
    {
        $config = $this->configs;
        foreach (explode('.', $name) as $key) {
            if (isset($config[$key])) {
                $config = $config[$key];
            } else {
                return $default;
            }
        }
        return $config;
    }

    public function configs(array $configs = []): array
    {
        return $this->configs = array_merge($this->configs, $configs);
    }
}