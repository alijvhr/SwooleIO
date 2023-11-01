<?php

namespace SwooleIO\EngineIO;

/**
 * Class Packet
 *
 * @method static static parse(string $packet)
 *
 */
class Packet
{

    /**
     * Engine.io packet types.
     */
    const types = [
        'open',
        'close',
        'ping',
        'pong',
        'message',
        'upgrade',
        'noop',
    ];

    protected string $packet = '';
    protected string $payload;
    protected bool $valid;
    protected int $packet_type;
    protected int $id;

    public function __construct(string $packet = null)
    {
        if (isset($packet)) {
            $this->packet = $packet;
            try {
                $this->parse();
            } catch (InvalidPacketException $e) {
                $this->valid = false;
            }
        }
    }

    /**
     * @throws InvalidPacketException
     */
    protected function parse(): self
    {
        if (isset($this->valid)) return $this;
        $packet = $this->packet;
        $type = $packet[0] ?? '';
        if (is_numeric($type) && isset(self::types[$type])) {
            $this->payload = substr($packet, 1);
            $this->packet_type = +$type;
            $this->valid = true;
        } else {
            $this->valid = false;
            throw new InvalidPacketException();
        }
        return $this;
    }

    public static function __callStatic($name, $args)
    {
        if ($name === 'parse' && count($args) == 1) {
            $object = new static($args[0]);
            $object->parse();
            return $object;
        }
    }

    public static function create(string $type): self
    {
        $type_id = array_search($type, self::types);
        if ($type_id === false)
            throw new InvalidPacketException();
        $object = new static();
        $object->packet_type = $type_id;
        return $object;
    }

    public function getPacketType(bool $as_int)
    {
        return $as_int ? $this->packet_type : self::types[$this->packet_type];
    }

    public function getPayload(): ?string
    {
        return $this->payload;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }
}