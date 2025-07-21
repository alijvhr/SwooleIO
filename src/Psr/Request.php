<?php

declare(strict_types=1);
/**
 * This file is part of OpenSwoole.
 * @link     https://openswoole.com
 * @contact  hello@openswoole.com
 */

namespace SwooleIO\Psr;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

class Request extends Message implements RequestInterface
{
    private ?string $method;

    private UriInterface $uri;

    private ?string $requestTarget;

    public function __construct($uri, string $method = null, $body = null, array $headers = [], string $protocolVersion = '1.1')
    {
        $this->uri = is_string($uri) ? new Uri($uri) : $uri;
        $this->method = $method;
        foreach ($headers as $name => $value)
            $headers[strtolower($name)] = is_array($value) ? $value : [$value];
        $this->headers = $headers;
        $this->protocolVersion = $protocolVersion;
        parent::__construct(Stream::from($body));
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod($method): RequestInterface
    {
        if (!is_string($method)) {
            throw new InvalidArgumentException('Method is not validate.');
        }
        $this->method = $method;
        return $this;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri($uri, $preserveHost = false): RequestInterface
    {
        if ($uri === $this->uri) {
            return $this;
        }

        $this->uri = $uri;

        if ($preserveHost && $this->hasHeader('Host')) {
            return $this;
        }

        $uriHost = $uri->getHost();

        if (empty($uriHost)) {
            return $this;
        }

        if ($uri->getPort()) {
            $uriHost .= ':' . $uri->getPort();
        }

        return $this->withHeader('Host', $uriHost);
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if (!empty($query = $this->uri->getQuery())) {
            $target .= '?' . $query;
        }

        if (empty($target)) {
            $target = '/';
        }

        return $target;
    }

    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        if (preg_match('/\s/', $requestTarget)) {
            throw new InvalidArgumentException('Request target can\'t contain whitespaces');
        }

        $this->requestTarget = $requestTarget;
        return $this;
    }
}
