<?php

declare(strict_types=1);
/**
 * This file is part of OpenSwoole.
 * @link     https://openswoole.com
 * @contact  hello@openswoole.com
 */

namespace SwooleIO\Psr;

use InvalidArgumentException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

class Message implements MessageInterface
{
    public array $headers = [];

    protected string $protocolVersion = '1.1';

    protected StreamInterface $body;

    public function __construct(StreamInterface $stream = null)
    {
        if ($stream === null) {
            $stream = new Stream('php://memory', 'wb+');
        }
        $this->body = $stream;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): MessageInterface
    {
        $this->protocolVersion = $version;
        return $this;
    }

    public function getHeaders(): array
    {
        $headers = [];
        foreach ($this->headers as $header => $line) {
            $headers[$header] = is_array($line) ? $line : [$line];
        }
        return $headers;
    }

    protected function setHeaders(array $headers): void
    {
        $this->headers = $this->withHeaders($headers)
            ->getHeaders();
    }

    public function withHeaders(array $headers): MessageInterface
    {
        foreach ($headers as $key => $header) {
            if (is_array($header)) {
                foreach ($header as $item) {
                    $this->withAddedHeader($key, $item);
                }
            } else {
                $this->withHeader($key, $header);
            }
        }

        return $this;
    }

    public function withAddedHeader(string $name, $value): MessageInterface
    {
        if (!is_string($value) && !is_array($value) || empty($name) || $value !== '' && $value !== '0' && empty($value)) {
            throw new InvalidArgumentException('Header is not validate.');
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->headers[strtolower($name)][] = $item;
            }
        } else {
            $this->headers[strtolower($name)][] = $value;
        }
        return $this;
    }

    public function withHeader(string $name, $value): MessageInterface
    {
        if (!is_string($value) && !is_array($value) || $name === '' || $value !== '' && empty($value)) {
            throw new InvalidArgumentException('Header is not validate.');
        }
        $this->headers[strtolower($name)] = is_array($value) ? $value : [$value];

        return $this;
    }

    public function getHeaderLine(string $name): string
    {
        $value = $this->getHeader($name);

        return implode(',', $value);
    }

    public function getHeader(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    public function withoutHeader(string $name): MessageInterface
    {
        $name = strtolower($name);

        if (!$this->hasHeader($name)) {
            return $this;
        }

        unset($this->headers[$name]);

        return $this;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): MessageInterface
    {
        $this->body = $body;
        return $this;
    }
}
