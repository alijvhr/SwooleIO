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

    /** @var Packet[] */
    protected array $next = [];
    protected string $payload;
    protected bool $valid;
    protected int $engine_type;
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
            $this->engine_type = +$type;
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

    public static function create(string $type, ...$data): self
    {
        $type_id = array_search($type, self::types);
        if ($type_id === false)
            throw new InvalidPacketException();
        $object = new static();
        $object->engine_type = $type_id;
        $object->payload = json_encode(count($data[0]) == 1 ? $data[0] : $data);
        return $object;
    }

    public function getEngineType(bool $as_int = false)
    {
        return $as_int ? $this->engine_type : self::types[$this->engine_type];
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


    public function __toString()
    {
        return $this->encode();
    }

    public function encode(): string
    {
        foreach ($this->next as $packet) {
            $this->payload .= chr(30) . $packet->encode();
        }
        return "$this->engine_type$this->payload";
    }

    public function append(Packet $packet): Packet
    {
        $this->next[] = $packet;
        return $this;
    }
}