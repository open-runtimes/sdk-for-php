<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\DTO;

use OpenRuntimes\Orchestrator\Enum\JobState;

final readonly class JobResponse
{
    public function __construct(
        public string $id,
        public JobState $status,
    ) {}

    /**
     * @param  array{id: string, status: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['id'], JobState::from($data['status']));
    }
}
