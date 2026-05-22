<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\DTO;

final readonly class JobList
{
    /**
     * @param  list<JobStatus>  $jobs
     */
    public function __construct(public array $jobs) {}

    /**
     * @param  array{jobs: list<array{id: string, status: string, exitCode?: int|null, error?: string}>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(\array_map(JobStatus::fromArray(...), $data['jobs']));
    }
}
