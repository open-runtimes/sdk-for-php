<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Callback;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use OpenRuntimes\Orchestrator\Exception\ClientException;

final readonly class CloudEvent
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $specVersion,
        public string $type,
        public string $source,
        public string $subject,
        public string $id,
        public DateTimeInterface $time,
        public string $dataContentType,
        public array $data,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $data = $payload['data'] ?? [];
        if (! isset($payload['time']) || ! \is_string($payload['time']) || $payload['time'] === '') {
            throw new ClientException('Invalid CloudEvent: missing string time.');
        }

        try {
            $time = new DateTimeImmutable($payload['time']);
        } catch (Exception $e) {
            throw new ClientException('Invalid CloudEvent: malformed time.', previous: $e);
        }

        return new self(
            specVersion: (string) ($payload['specversion'] ?? ''),
            type: (string) ($payload['type'] ?? ''),
            source: (string) ($payload['source'] ?? ''),
            subject: (string) ($payload['subject'] ?? ''),
            id: (string) ($payload['id'] ?? ''),
            time: $time,
            dataContentType: (string) ($payload['datacontenttype'] ?? ''),
            data: \is_array($data) ? $data : [],
        );
    }
}
