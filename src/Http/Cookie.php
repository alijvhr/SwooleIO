<?php

namespace SwooleIO\Http;

use DateTime;
use Stringable;

class Cookie
{

    public function __construct(
        public string    $name,
        public mixed     $value = '',
        public ?DateTime $expires = null,
        public string    $path = '/',
        public ?string   $domain = null,
        public bool      $secure = true,
        public bool      $httponly = true,
        public string    $samesite = 'Lax',
        public string    $priority = '',
        public bool      $partitioned = false
    )
    {
    }

    public static function from(string $name, string $value): self
    {
        $decoded = match (true) {
            preg_match('/^["\'{\[]/', $value) => json_decode($value, true),
            preg_match('/^\w:/', $value)      => @unserialize($value),
            default                           => $value,
        };
        return new self($name, $decoded ?: $value);
    }

    public function save(): void
    {
        response()?->withCookie($this->name, $this->__toString(), $this->options());
        co_get('_cookie')[$this->name] = $this->value;
    }

    public function delete(): void
    {
        $options = $this->options();
        $options['expires'] = 1;
        response()?->withCookie($this->name, 'deleted', $options);
        unset(co_get('_cookie')[$this->name]);
    }

    /**
     * @return array
     */
    protected function options(): array
    {
        $options = [
            'path'   => $this->path,
            'domain' => $this->domain ?? '.' . (request()?->getUri()->getHost() ?? ''),
        ];
        if ($this->expires) {
            $options['expires'] = $this->expires->getTimestamp();
        }
        if ($this->secure) {
            $options['secure'] = $this->secure;
        }

        if ($this->httponly) {
            $options['httponly'] = true;
        }

        if ($this->samesite !== '') {
            $options['samesite'] = $this->samesite;
        }

        if ($this->priority !== '') {
            $options['priority'] = $this->priority;
        }

        if ($this->partitioned) {
            $options['partitioned'] = true;
        }
        return $options;
    }

    public function __toString(): string
    {
        return is_scalar($this->value) || $this->value instanceof Stringable ? (string)$this->value : serialize($this->value);
    }
}
