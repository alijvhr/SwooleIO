<?php

namespace SwooleIO;

if (!function_exists('io')) {
    function io(): IO
    {
        return IO::instance();
    }
}

if (!function_exists('uuid')) {
    function uuid(): string
    {
        try {
            $out = bin2hex(random_bytes(18));
        } catch (\Exception $e) {
            $out = '';
            for ($i = 0; $i < 16; $i++) $out .= chr(rand(12, 240));
            $out = dechex($out);
        }

        $out[8] = "-";
        $out[13] = "-";
        $out[18] = "-";
        $out[23] = "-";

        $out[14] = "4";

        try {
            $out[19] = ["8", "9", "a", "b"][random_int(0, 3)];
        } catch (\Exception $e) {
            $out[19] = ["8", "9", "a", "b"][rand(0, 3)];
        }

        return $out;
    }
}

if (!function_exists('interpolate')) {

    function interpolate(string $message, array $context = []): string
    {
        $replace = [];
        foreach ($context as $key => $val)
            $replace["\{$key}"] = $val;
        return strtr($message, $replace);
    }
}