<?php

namespace SwooleIO\EngineIO;

/**
 * Class Packet
 *
 * @method static static parse(string $packet)
 *
 */
class Packet implements \Iterator
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
    protected array $order = [];
    protected int $index = 0;
    protected self $iterator;
    protected string $payload;
    protected bool $valid;
    protected int $engine_type;
    protected int $id;

    public function __construct(string $packet = null)
    {
        $this->order = [$this];
        $this->index = 0;
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
        $payloads = explode(chr(30), $this->packet);
        $payload = $payloads[0];
        $type = $payload[0] ?? '';
        $this->packet = $payload;
        if (is_numeric($type) && isset(self::types[$type])) {
            $this->payload = substr($payload, 1);
            $this->engine_type = +$type;
            $this->valid = true;
        } else {
            $this->valid = false;
            throw new InvalidPacketException();
        }
        for ($i = 1; $i < count($payloads); $i++) {
            $packet = new static($payloads[$i]);
            $this->append($packet);
        }
        return $this;
    }

    public function append(Packet $packet): Packet
    {

        $packet->order = &$this->order;
        $packet->index = count($this->order);
        $this->order[] = $packet;
        return $this;
    }

    public static function __callStatic($name, $args)
    {
        if ($name === 'parse' && count($args) == 1) {
            $object = new static($args[0]);
            $object->parse();
            return $object;
        }
        return null;
    }

    public static function create(string $type, ...$data): self
    {
        $type_id = array_search($type, self::types);
        if ($type_id === false) $type_id = -1;
        $object = new static();
        $object->engine_type = $type_id;
        $count = count($data);
        if ($count == 1)
            $data = $data[0];
        elseif ($count == 0) $data = '';
        $object->payload = is_array($data) ? json_encode($data) : $data;
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

    public function encode(bool $all = false): string
    {
        if ($all)
            foreach ($this->order as $packet) {
                $this->payload .= chr(30) . $packet->encode();
            }
        return "$this->engine_type$this->payload";
    }

    public function next(): void
    {
        $this->iterator = $this->order[$this->index + 1] ?? null;
    }

    public function current(): self
    {
        return $this->iterator;
    }

    public function key(): int
    {
        return $this->iterator->index;
    }

    public function valid(): bool
    {
        return isset($this->iterator);
    }

    public function rewind()
    {
        $this->iterator = $this->order[0];
    }
}