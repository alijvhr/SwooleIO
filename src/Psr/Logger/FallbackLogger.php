<?php

namespace SwooleIO\Psr\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Toolkit\Cli\Util\Clog;
use function SwooleIO\interpolate;

class FallbackLogger implements LoggerInterface
{

    use LoggerTrait;

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        Clog::log($level, interpolate($message, $context));
    }
}