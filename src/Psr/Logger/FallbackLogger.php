<?php

namespace SwooleIO\Psr\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Stringable;
use Throwable;
use Toolkit\Cli\Util\Clog;
use function SwooleIO\interpolate;

class FallbackLogger implements LoggerInterface
{

    use LoggerTrait;

    public function error(\Stringable|string|Throwable $message, array $context = []): void
    {
        if ($message instanceof Throwable) {
            $message = "{$message->getMessage()}({$message->getFile()}:{$message->getLine()})";
        }
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $this->log(LogLevel::ERROR, "$message [{$trace[0]['file']}:{$trace[0]['line']}]", $context);
    }

    public function log(string $level, string|Stringable $message, array $context = []): void
    {
        Clog::log($level, interpolate($message, $context));
    }
}