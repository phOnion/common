<?php

declare(strict_types=1);

namespace Onion\Framework;

class Json
{
    public static function encode($data, bool $pretty = false): string
    {
        $options = JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $options |= JSON_PRETTY_PRINT;
        }

        return json_encode($data, $options);
    }

    public static function decode(string $data): array
    {
        return json_decode($data, true, flags: JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
    }
}
