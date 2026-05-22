<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Callback;

use DateTimeImmutable;
use DateTimeInterface;

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

        return new self(
            specVersion: (string) ($payload['specversion'] ?? ''),
            type: (string) ($payload['type'] ?? ''),
            source: (string) ($payload['source'] ?? ''),
            subject: (string) ($payload['subject'] ?? ''),
            id: (string) ($payload['id'] ?? ''),
            time: new DateTimeImmutable((string) ($payload['time'] ?? 'now')),
            dataContentType: (string) ($payload['datacontenttype'] ?? ''),
            data: \is_array($data) ? $data : [],
        );
    }
}
