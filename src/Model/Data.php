<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

use BackedEnum;
use OpenRuntimes\Orchestrator\Exception\ClientException;

/**
 * Typed reads out of a decoded orchestrator response, each failing with a
 * ClientException naming the field and the response it came from.
 *
 * @internal
 */
final readonly class Data
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function string(array $data, string $key, string $context): string
    {
        $value = $data[$key] ?? null;
        if (! \is_string($value) || $value === '') {
            throw new ClientException("Invalid {$context}: missing string {$key}.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function optionalString(array $data, string $key, string $context): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }

        if (! \is_string($value)) {
            throw new ClientException("Invalid {$context}: {$key} must be a string.");
        }

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function int(array $data, string $key, string $context, int $default = 0): int
    {
        return self::optionalInt($data, $key, $context) ?? $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function optionalInt(array $data, string $key, string $context): ?int
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }

        if (! \is_int($value)) {
            throw new ClientException("Invalid {$context}: {$key} must be an integer.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function optionalFloat(array $data, string $key, string $context): ?float
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }

        if (! \is_float($value) && ! \is_int($value)) {
            throw new ClientException("Invalid {$context}: {$key} must be a number.");
        }

        return (float) $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function strings(array $data, string $key, string $context): array
    {
        $value = $data[$key] ?? [];
        if (! \is_array($value)) {
            throw new ClientException("Invalid {$context}: {$key} must be an array of strings.");
        }

        foreach ($value as $item) {
            if (! \is_string($item)) {
                throw new ClientException("Invalid {$context}: {$key} must be an array of strings.");
            }
        }

        /** @var list<string> */
        return \array_values($value);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public static function stringMap(array $data, string $key, string $context): array
    {
        $value = $data[$key] ?? [];
        if (! \is_array($value)) {
            throw new ClientException("Invalid {$context}: {$key} must be an object of strings.");
        }

        foreach ($value as $item) {
            if (! \is_string($item)) {
                throw new ClientException("Invalid {$context}: {$key} must be an object of strings.");
            }
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function objects(array $data, string $key, string $context): array
    {
        $value = $data[$key] ?? [];
        if (! \is_array($value)) {
            throw new ClientException("Invalid {$context}: {$key} must be an array of objects.");
        }

        foreach ($value as $item) {
            if (! \is_array($item)) {
                throw new ClientException("Invalid {$context}: each entry of {$key} must be an object.");
            }
        }

        /** @var list<array<string, mixed>> */
        return \array_values($value);
    }

    /**
     * @template T of BackedEnum
     *
     * @param  array<string, mixed>  $data
     * @param  class-string<T>  $enum
     * @return T
     */
    public static function enum(array $data, string $key, string $enum, string $context): BackedEnum
    {
        $value = self::string($data, $key, $context);
        $case = $enum::tryFrom($value);

        if ($case === null) {
            throw new ClientException("Invalid {$context}: unknown {$key} \"{$value}\".");
        }

        return $case;
    }
}
