<?php

declare(strict_types=1);
/**
 * This file is part of OpenSwoole.
 * @link     https://openswoole.com
 * @contact  hello@openswoole.com
 */

namespace SwooleIO\Psr;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

class Response extends Message implements ResponseInterface
{
    public const CHUNK_SIZE = 100 * 1024 * 1024; // 100K
    private static $statusPhrases = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',

        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',

        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',

        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        444 => 'Connection Closed Without Response',
        451 => 'Unavailable For Legal Reasons',

        499 => 'Client Closed Request',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
        599 => 'Network Connect Timeout Error',
    ];
    private $statusCode;
    private $reasonPhrase;
    protected $cookies = [];

    public function __construct($body, int $statusCode = 200, string $reasonPhrase = '', array $headers = [], string $protocolVersion = '1.1')
    {
        $this->body = is_string($body) ? Stream::from($body) : $body;
        $this->setStatusCode($statusCode);
        $this->setReasonPhrase($reasonPhrase);
        $this->setHeaders($headers);
        $this->protocolVersion = $protocolVersion;
    }

    public static function emit(\Swoole\HTTP\Response $response, $psrResponse)
    {
        $response->status($psrResponse->getStatusCode());
        foreach ($psrResponse->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $response->setHeader($name, $value);
            }
        }
        foreach ($psrResponse->cookies as $name => [$value, $options]) {
            $response->setCookie($name, $value, ...$options);
        }
        $body = $psrResponse->getBody();
        $body->rewind();
        if ($body->getSize() > static::CHUNK_SIZE) {
            while (!$body->eof()) {
                $response->write($body->read(static::CHUNK_SIZE));
            }
            $response->end();
        } else {
            $response->end($body->getContents());
        }
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus($code, $reasonPhrase = ''): self
    {
        if (!is_int($code) && !is_string($code) || !array_key_exists($code, static::$statusPhrases)) {
            throw new InvalidArgumentException('Error HTTP status code.');
        }
        $this->setStatusCode($code);
        $this->setReasonPhrase($reasonPhrase);

        return $this;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function withCookie(string $name, string $value, array $options = []): void
    {
        $this->cookies[$name] = [$value, $options];
//        $cookieString = urlencode($name) . '=' . urlencode($value) . ';';
//
//        foreach ($options as $name => $value) {
//            $name = strtolower($name);
//            if (in_array($name, ['path', 'domain', 'expires', 'secure', 'httponly', 'samesite'])) {
//                $cookieString .= isset($value) ? "$name=$value; " : "$name; ";
//            }
//        }
//
//        $cookieString = rtrim($cookieString, '; ');
//        $this->withAddedHeader('set-cookie', $cookieString);
//        return $cookieString;
    }

    private function setStatusCode(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    private function setReasonPhrase(string $reasonPhrase): void
    {
        if ($reasonPhrase === '' && array_key_exists($this->statusCode, static::$statusPhrases)) {
            $reasonPhrase = static::$statusPhrases[$this->statusCode];
        }

        $this->reasonPhrase = $reasonPhrase;
    }
}
