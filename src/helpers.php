<?php

/** @noinspection JsonEncodingApiUsageInspection,PhpFunctionNamingConventionInspection,RandomApiMigrationInspection */

use Sparrow\Modules\DateTime;
use SwooleIO\IO;
use SwooleIO\Memory\ContextManager;
use SwooleIO\Psr\Response;
use SwooleIO\Psr\ServerRequest;
use SwooleIO\Service;
use SwooleIO\Service\ServiceProxy;

function io(?string $ID = null): IO
{
    $io = IO::instance();
    if (isset($ID)) {
        $io->id($ID);
    }
    return $io;
}

function debug(mixed $msg, array $flags = []): void
{
    $json_flags = 0;
    foreach ($flags as $flag) {
        switch ($flag) {
            case 'json_pretty_print':
                $json_flags |= JSON_PRETTY_PRINT;
        }
    }
    if (!is_string($msg)) {
        $msg = json_encode($msg, $json_flags);
    }
    IO::instance()->log->debug($msg);
}

function uuid(): string
{
    try {
        $out = bin2hex(random_bytes(18));
    } catch (Exception $e) {
        $out = '';
        for ($i = 0; $i < 16; $i++) {
            $out .= chr(rand(12, 240));
        }
        $out = dechex($out);
    }

    $out[8] = '-';
    $out[13] = '-';
    $out[18] = '-';
    $out[23] = '-';

    $out[14] = '4';

    try {
        $out[19] = ['8', '9', 'a', 'b'][random_int(0, 3)];
    } catch (Exception $e) {
        $out[19] = ['8', '9', 'a', 'b'][rand(0, 3)];
    }

    return $out;
}


function interpolate(string $message, array $context = []): string
{
    $replace = [];
    foreach ($context as $key => $val) $replace['{' . $key . '}'] = $val;
    return strtr($message, $replace);
}


function request(): ?ServerRequest
{
    return co_get('request');
}

function response(): ?Response
{
    return co_get('response');
}


function &co_get(string $name, bool $init = true): mixed
{
    return ContextManager::get($name, $init);
}

function &co_set(string $name, mixed $value, bool $add = false): mixed
{
    if ($add) {
        $ctx = &ContextManager::get($name);
        $ctx += $value;
        return $ctx;
    }
    return ContextManager::set($name, $value);
}

function _get(string|null $key = null, mixed $default = null): mixed
{
    $var = co_get('_get');
    return isset($key) ? $var[$key] ?? $default : $var;
}

function _post(?string $key = null, mixed $default = null): mixed
{
    $var = co_get('_post');
    return isset($key) ? $var[$key] ?? $default : $var;
}

function _files(string $key, mixed $default = null): mixed
{
    $var = co_get('_files');
    return $var[$key] ?? $default;
}

function _cookie(string $key, mixed $default = null): mixed
{
    $var = co_get('_cookie');
    return $var[$key] ?? $default;
}

function cookieSet(string $name, string $value, DateTime|null $expires = null, string $path = '/', string|null $domain = null, bool|null $secure = null): void
{
    $options = [
        'path'   => $path ?? '/',
        'domain' => '.' . ($domain ?? request()?->getUri()->getHost()),
    ];
//        if (isset($expires)) $options['expires'] = $expires->toLocale('D, d M Y H:i:s. \G\M\T', 'en');
    if (isset($expires)) $options['expires'] = $expires->getTimestamp();
    if (isset($secure)) $options['secure'] = $secure;
    response()?->withCookie($name, $value, $options);
    co_get('_cookie')[$name] = $value;
}

function cookieDel(string $name, string $path = '/', string|null $domain = null, bool|null $secure = null): void
{
    $options = [
        'path'   => $path ?? '/',
        'domain' => '.' . ($domain ?? request()?->getUri()->getHost()),
    ];
    if (isset($secure)) {
        $options['secure'] = $secure;
    }
    response()?->withCookie($name, '', [...$options, 'expires' => 0]);
    unset(co_get('_cookie')[$name]);
}

/**
 * @param class-string<Service> $service
 */
function service(string $service, string|int|null $id = null): ServiceProxy|null
{
    return !is_null(io()->services->get($service)) ? ServiceProxy::for($service, $id) : null;
}