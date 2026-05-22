<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\DTO;

use OpenRuntimes\Orchestrator\Enum\JobState;

final readonly class JobStatus
{
    public function __construct(
        public string $id,
        public JobState $status,
        public ?int $exitCode = null,
        public ?string $error = null,
    ) {}

    /**
     * @param  array{id: string, status: string, exitCode?: int|null, error?: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            status: JobState::from($data['status']),
            exitCode: $data['exitCode'] ?? null,
            error: $data['error'] ?? null,
        );
    }
}
