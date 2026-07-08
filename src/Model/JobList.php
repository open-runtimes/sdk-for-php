<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

use OpenRuntimes\Orchestrator\Exception\ClientException;

final readonly class JobList
{
    /**
     * @param  list<JobStatus>  $jobs
     */
    public function __construct(public array $jobs) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['jobs']) || ! \is_array($data['jobs'])) {
            throw new ClientException('Invalid job list: missing jobs array.');
        }

        foreach ($data['jobs'] as $job) {
            if (! \is_array($job)) {
                throw new ClientException('Invalid job list: each job must be an object.');
            }
        }

        return new self(\array_values(\array_map(JobStatus::fromArray(...), $data['jobs'])));
    }
}
