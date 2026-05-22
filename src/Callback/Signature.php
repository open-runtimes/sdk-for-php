<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Callback;

final readonly class Signature
{
    public static function sign(string $body, string $key): string
    {
        return 'sha256='.\hash_hmac('sha256', $body, $key);
    }

    public static function verify(string $body, string $signature, string $key): bool
    {
        return $signature !== '' && \hash_equals(self::sign($body, $key), $signature);
    }
}
