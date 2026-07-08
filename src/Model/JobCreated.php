<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

use OpenRuntimes\Orchestrator\Enum\JobState;
use OpenRuntimes\Orchestrator\Exception\ClientException;

final readonly class JobCreated
{
    public function __construct(
        public string $id,
        public JobState $status,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['id']) || ! \is_string($data['id'])) {
            throw new ClientException('Invalid job response: missing string id.');
        }

        if (! isset($data['status']) || ! \is_string($data['status'])) {
            throw new ClientException('Invalid job response: missing string status.');
        }

        $status = JobState::tryFrom($data['status']);
        if (! $status instanceof JobState) {
            throw new ClientException("Invalid job response: unknown status \"{$data['status']}\".");
        }

        return new self($data['id'], $status);
    }
}
