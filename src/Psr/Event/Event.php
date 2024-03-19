<?php

namespace SwooleIO\Psr\Event;

use Psr\EventDispatcher\StoppableEventInterface;

class Event implements StoppableEventInterface
{
    /**
     * @var bool Whether no further event listeners should be triggered
     */
    private bool $propagationStopped = false;

    public static function with(array $data): static
    {
        $event = new static();
        foreach ($data as $key => $value)
            $event->$key = $value;
        return $event;
    }

    /**
     * Is propagation stopped?
     *
     * This will typically only be used by the Dispatcher to determine if the
     * previous listener halted propagation.
     *
     * @return bool
     *   True if the Event is complete and no further listeners should be called.
     *   False to continue calling listeners.
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Stops the propagation of the event to further event listeners.
     *
     * If multiple event listeners are connected to the same event, no
     * further event listener will be triggered once any trigger calls
     * stopPropagation().
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}