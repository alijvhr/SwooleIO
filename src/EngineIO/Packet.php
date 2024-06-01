<?php

namespace SwooleIO\EngineIO;

use Iterator;
use SwooleIO\Constants\EioPacketType;
use SwooleIO\Exceptions\InvalidPacketException;
use TypeError;
use function SwooleIO\debug;

class Packet implements Iterator
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
                $this->valid = true;
            } catch (InvalidPacketException $e) {
                $this->valid = false;
            }
        } else $this->valid = true;
    }

    public static function from(string $packet): ?static
    {
        return new static($packet);
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

    public function append(Packet $packet): Packet
    {

        $packet->order = &$this->order;
        $packet->index = count($this->order);
        $this->order[] = $packet;
        return $this;
    }

    public function getEngineType(bool $as_int = false): int|EioPacketType|null
    {
        return $this->valid ? ($as_int ? $this->engine_type->value : $this->engine_type) : null;
    }

    public function getPayload(): ?string
    {
        return $this->valid ? $this->payload : null;
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

    /**
     * @throws InvalidPacketException
     */
    protected function parse(): self
    {
        if (isset($this->valid)) return $this;
        $payloads = explode(chr(30), $this->packet);
        $payload = $payloads[0];
        $this->packet = $payload;
        try {
            $type = EioPacketType::tryFrom($payload[0] ?? -1);
        } catch (TypeError $e) {
            debug('Error EIO Type: ' . $payload);
            throw new InvalidPacketException();
        }
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
}