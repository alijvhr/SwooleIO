<?php

namespace SwooleIO\SocketIO;

use SwooleIO\EngineIO\InvalidPacketException;
use SwooleIO\EngineIO\Packet as EngineIOPacket;

/**
 * Class Packet
 *
 * @method static static parse(string $packet)
 *
 */
class Packet extends EngineIOPacket
{

    const reserved_events = [
        "connect",
        "connect_error",
        "disconnect",
        "disconnecting",
        "newListener",
        "removeListener",
    ];

    const types = [
        'connect',
        'disconnect',
        'event',
        'ack',
        'error',
        'binary_event',
        'binary_ack',
    ];

    protected string $packet = '';
    protected array $params;
    protected $data;
    protected string $event;
    protected int $socket_type;

    protected string $namespace;
    protected array $binary_attachments;
    protected int $binary_count;
    protected int $id;

    public function __construct(string $packet = null)
    {
        parent::__construct($packet);
    }

    public static function create(string $type, ...$data): self
    {
        $type_id = array_search($type, self::types);
        if ($type_id === false)
            throw new InvalidPacketException();
        $object = new static();
        $object->engine_type = 4;
        $object->socket_type = $type_id;
        $object->namespace = '/';
        if ($type_id == 2) {
            $object->event = $data[0];
            $object->params = array_slice($data, 1);
            $object->data = $data;
        } else {
            $object->params = $data;
            $object->data = $data;
        }

        return $object;
    }

    /**
     * Get socket packet type of raw payload.
     *
     * @return string
     */
    public function getSocketType(bool $as_int)
    {
        if ($this->engine_type == 4)
            return $as_int ? $this->socket_type : self::types[$this->socket_type];
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
    public function getData()
    {
        return $this->data;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function __toString()
    {
        return $this->encode();
    }

    public function encode(): string
    {
        $this->engine_type = 4;
        $id = $this->id ?? '';
        $namespace = "$this->namespace,";
        $data = isset($this->data) && $this->data ? json_encode($this->data) : '';
        if (in_array($this->socket_type, [2, 3, 5, 6]))
            $this->payload = "$this->socket_type$namespace$id$data";
        else
            $this->payload = "$this->socket_type$data";
        return parent::encode();
    }

    protected function parse(): self
    {
        parent::parse();
        $packet = $this->payload;
        $valid = true;
        if ($this->engine_type === 4) {
            preg_match('#^(\d)((?:\d++-)?)((?:/[^,]++,)?)((?:\d++)?)(.*+)$#ism', $packet, $parts);
            $this->socket_type = +$parts[1];
            $this->binary_attachments = [];
            $this->binary_count = +substr($parts[2], 0, -1);
            $this->namespace = substr($parts[3], 0, -1);
            $this->id = $parts[4];
            $payload = json_decode($parts[5], true);
            $valid = false;
            switch ($this->socket_type) {
                case 6:
                case 3:
                case 0:
                    $valid = is_array($payload);
                    $this->data = $payload;
                    break;
                case 1:
                    $valid = $payload == '';
                    break;
                case 4:
                    $valid = is_string($payload) || is_array($payload);
                    $this->data = $payload;
                    break;
                case 2:
                case 5:
                    $valid = is_array($payload) && (is_numeric($payload[0]) || (is_string($payload[0]) && !in_array($payload[0], self::reserved_events)));
                    $this->event = $payload[0];
                    $this->params = array_slice($payload, 1);
                    $this->data = $payload;
                    break;
            }
        }
        $this->valid = $valid;
        if (!$valid) throw new InvalidPacketException();
        return $this;
    }
}