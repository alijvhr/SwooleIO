<?php

namespace SwooleIO\SocketIO;

use SwooleIO\Constants\EioPacketType;
use SwooleIO\Constants\SioPacketType;
use SwooleIO\EngineIO\Packet as EioPacket;
use SwooleIO\Exceptions\InvalidPacketException;

/**
 * Class Packet
 *
 * @method static static parse(string $packet)
 *
 */
class Packet extends EioPacket
{


    protected string $packet = '';
    protected array $params;
    protected mixed $data;
    protected string $event;
    protected ?SioPacketType $socket_type;

    protected string $namespace;
    protected array $binary_attachments;
    protected int $binary_count;
    protected int $id;

    public function __construct(string $packet = null)
    {
        parent::__construct($packet);
    }

    public static function create(SioPacketType|EioPacketType $type, ...$data): self|EioPacket
    {
        if ($type instanceof EioPacketType)
            return parent::create($type, ...$data);
        $object = new self();
        $object->engine_type = EioPacketType::message;
        $object->socket_type = $type;
        $object->namespace = '/';
        if (in_array($type, [SioPacketType::event, SioPacketType::binary_event])) {
            $object->event = $data[0];
            $object->params = array_slice($data, 1);
            $object->data = $data;
        } else {
            $object->params = $data;
            $object->data = $data[0];
        }
        return $object;
    }

    /**
     * Get socket packet type of raw payload.
     *
     * @param bool $as_int
     * @return int|SioPacketType|null
     */
    public function getSocketType(bool $as_int = false): int|SioPacketType|null
    {
        if ($this->engine_type == EioPacketType::message)
            return $as_int ? $this->socket_type->value : $this->socket_type;
        return null;
    }

    public function getBinaryAttachments(): array
    {
        return $this->binary_attachments;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function setNamespace(string $namespace = '/'): string
    {
        return $this->namespace = $namespace;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data ?? null;
    }

    public function getParams(): array
    {
        return $this->params ?? [];
    }

    public function getEvent(): string
    {
        return $this->event ?? '';
    }

    public function __toString(): string
    {
        return $this->encode();
    }

    public function encode(bool $all = false): string
    {
        if (!$all) {
            $this->engine_type = EioPacketType::message;
            $id = $this->id ?? '';
            $namespace = "$this->namespace,";
            $data = isset($this->data) && $this->data ? json_encode($this->data) : '';
            $this->payload = $this->socket_type->value . $namespace . $id . $data;
        }
        return parent::encode($all);
    }

    protected function parse(): self
    {
        parent::parse();
        if ($this->valid && $this->engine_type == EioPacketType::message) {
            preg_match('#^(\d)((?:\d++-)?)((?:/[^,]++,)?)((?:\d++)?)(.*+)$#ism', $this->payload, $parts);
            $this->socket_type = SioPacketType::tryFrom($parts[1]);
            $this->binary_attachments = [];
            $this->binary_count = +(substr($parts[2], 0, -1) ?: 0);
            $this->namespace = substr($parts[3], 0, -1) ?: '/';
            $this->id = $parts[4] ?: 0;
            $payload = json_decode($parts[5], true);
            $valid = false;
            switch ($this->socket_type) {
                case SioPacketType::binary_ack:
                case SioPacketType::ack:
                case SioPacketType::connect:
                    $valid = is_array($payload);
                    $this->data = $payload;
                    break;
                case SioPacketType::disconnect:
                    $valid = $payload == '';
                    break;
                case SioPacketType::error:
                    $valid = is_string($payload) || is_array($payload);
                    $this->data = $payload;
                    break;
                case SioPacketType::event:
                case SioPacketType::binary_event:
                    $valid = is_array($payload) && (is_numeric($payload[0]) || (is_string($payload[0])));
                    $this->event = $payload[0];
                    $this->params = array_slice($payload, 1);
                    $this->data = $payload;
                    break;
            }
            $this->valid = $valid;
            if (!$valid) throw new InvalidPacketException();
        }
        return $this;
    }
}