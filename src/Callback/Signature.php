<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Callback;

use DateTimeImmutable;
use JsonException;
use OpenRuntimes\Orchestrator\Exception\ClientException;

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

    public static function verifyEvent(string $body, string $signature, string $key, int $toleranceSeconds = 300): bool
    {
        if (! self::verify($body, $signature, $key)) {
            return false;
        }

        try {
            $payload = \json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        if (! \is_array($payload)) {
            return false;
        }

        try {
            $event = CloudEvent::fromArray($payload);
        } catch (ClientException) {
            return false;
        }

        $now = new DateTimeImmutable;
        $age = \abs($now->getTimestamp() - $event->time->getTimestamp());

        return $age <= $toleranceSeconds;
    }
}
