<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Token;

use JsonException;
use OpenRuntimes\Orchestrator\Exception\ClientException;

final readonly class ArtifactToken
{
    /**
     * @return non-empty-string
     */
    public static function create(
        string $key,
        string $projectId,
        string $resourceId,
        string $deploymentId,
        string $purpose,
        int $ttl = 3600,
    ): string {
        $payload = [
            'projectId' => $projectId,
            'resourceId' => $resourceId,
            'deploymentId' => $deploymentId,
            'purpose' => $purpose,
            'expires' => \time() + $ttl,
        ];

        try {
            $encoded = self::base64UrlEncode(\json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            throw new ClientException("Failed to encode artifact token: {$e->getMessage()}", previous: $e);
        }

        $signature = self::sign($encoded, $key);

        return "{$encoded}.{$signature}";
    }

    public static function verify(
        string $key,
        string $token,
        string $projectId,
        string $resourceId,
        string $deploymentId,
        string $purpose,
    ): bool {
        try {
            $payload = self::payload($key, $token);
        } catch (ClientException) {
            return false;
        }

        return ! self::isExpired($payload)
            && ($payload['projectId'] ?? '') === $projectId
            && ($payload['resourceId'] ?? '') === $resourceId
            && ($payload['deploymentId'] ?? '') === $deploymentId
            && ($payload['purpose'] ?? '') === $purpose;
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(string $key, string $token): array
    {
        [$encoded, $signature] = \array_pad(\explode('.', $token, 2), 2, '');

        if ($encoded === '' || $signature === '' || ! \hash_equals(self::sign($encoded, $key), $signature)) {
            throw new ClientException('Invalid artifact token.');
        }

        try {
            $payload = \json_decode(self::base64UrlDecode($encoded), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ClientException('Invalid artifact token.', previous: $e);
        }

        if (! \is_array($payload)) {
            throw new ClientException('Invalid artifact token.');
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function isExpired(array $payload): bool
    {
        return (int) ($payload['expires'] ?? 0) < \time();
    }

    private static function sign(string $payload, string $key): string
    {
        return \hash_hmac('sha256', $payload, $key);
    }

    private static function base64UrlEncode(string $value): string
    {
        return \rtrim(\strtr(\base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $value .= \str_repeat('=', (4 - \strlen($value) % 4) % 4);

        return \base64_decode(\strtr($value, '-_', '+/')) ?: '';
    }
}
