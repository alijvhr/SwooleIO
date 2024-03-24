<?php

namespace SwooleIO\EngineIO;

use SwooleIO\Constants\EioPacketType;
use SwooleIO\Exceptions\InvalidPacketException;

class Packet implements \Iterator
{

    protected string $packet = '';

    /** @var Packet[] */
    protected array $order = [];
    protected int $index = 0;
    protected self $iterator;
    protected string $payload;
    protected bool $valid;
    protected EioPacketType $engine_type;
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
        $this->packet = $payload;
        $type = EioPacketType::tryFrom($payload[0] ?? -1);
        if (isset($type)) {
            $this->payload = substr($payload, 1);
            $this->engine_type = $type;
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

    public static function from(string $packet): ?static
    {
        try {
            $object = new static($packet);
            $object->parse();
            return $object;
        } catch (InvalidPacketException $e) {
            return null;
        }
    }

    public static function create(EioPacketType $type, ...$data): self
    {
        $object = new static();
        $object->engine_type = $type;
        $count = count($data);
        if ($count == 1)
            $data = $data[0];
        elseif ($count == 0) $data = '';
        $object->payload = is_array($data) ? json_encode($data) : $data;
        return $object;
    }

    public function getEngineType(bool $as_int = false): int|EioPacketType
    {
        return $as_int ? $this->engine_type->value : $this->engine_type;
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

    public function __toString(): string
    {
        return $this->encode();
    }

    public function encode(bool $all = false): string
    {
        return $all ? implode(chr(30), $this->order) : $this->engine_type->value . $this->payload;
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

    public function rewind(): void
    {
        $this->iterator = $this->order[0];
    }
}