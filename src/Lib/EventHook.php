<?php

namespace SwooleIO\Lib;

abstract class EventHook
{
    public function __construct(protected object $target, bool $registerNow = false)
    {
        if ($registerNow) $this->registerAll();
    }

    /**
     * Register all available event callbacks
     *
     * @return void
     */
    public function registerAll(): void
    {
        foreach ($this->all() as $event) {
            $this->target->on($event, [$this, "on$event"]);
        }
    }

    /**
     * List all methods starting with “on” in the class
     *
     * @return list<string>
     */
    public function all(): array
    {
        $class = new \ReflectionClass($this);
        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        $list = [];
        foreach ($methods as $method)
            if(!$method->isStatic() && preg_match('/^on\p{Lu}/', $method->name))
                $list[] = substr($method->name, 2);
        return $list;
        /*
         * inline style
         * array_reduce($methods, fn($list, $method) => [!$method->isStatic() && preg_match('/^on\p{Lu}/', $method->name) && $list[] = substr($method->name, 2), $list][1],[]);
         */
    }

    /**
     * Initialize an EventHook object and registers all the callbacks
     *
     * @param object $target
     * @return static
     */
    public static function register(object $target): static
    {
        return new static($target, true);
    }

}