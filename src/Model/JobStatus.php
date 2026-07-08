<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

use OpenRuntimes\Orchestrator\Enum\JobState;
use OpenRuntimes\Orchestrator\Exception\ClientException;

final readonly class JobStatus
{
    public function __construct(
        public string $id,
        public JobState $status,
        public ?int $exitCode = null,
        public ?string $error = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['id']) || ! \is_string($data['id'])) {
            throw new ClientException('Invalid job status: missing string id.');
        }

        if (! isset($data['status']) || ! \is_string($data['status'])) {
            throw new ClientException('Invalid job status: missing string status.');
        }

        $status = JobState::tryFrom($data['status']);
        if (! $status instanceof JobState) {
            throw new ClientException("Invalid job status: unknown status \"{$data['status']}\".");
        }

        $exitCode = $data['exitCode'] ?? null;
        if ($exitCode !== null && ! \is_int($exitCode)) {
            throw new ClientException('Invalid job status: exitCode must be an integer.');
        }

        $error = $data['error'] ?? null;
        if ($error !== null && ! \is_string($error)) {
            throw new ClientException('Invalid job status: error must be a string.');
        }

        return new self(
            id: $data['id'],
            status: $status,
            exitCode: $exitCode,
            error: $error,
        );
    }
}
