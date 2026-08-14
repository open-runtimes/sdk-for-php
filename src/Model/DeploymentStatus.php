<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

use OpenRuntimes\Orchestrator\Enum\DeploymentState;
use OpenRuntimes\Orchestrator\Enum\TrafficMode;

final readonly class DeploymentStatus
{
    /**
     * @param  list<string>  $revisions  Newest first; empty on the Docker backend, which is single-revision.
     * @param  list<TrafficTarget>  $traffic
     */
    public function __construct(
        public string $id,
        public DeploymentState $status,
        public string $url,
        public int $desiredReplicas = 0,
        public int $availableReplicas = 0,
        public array $revisions = [],
        public array $traffic = [],
        public ?TrafficMode $mode = null,
        public ?string $error = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $mode = Data::optionalString($data, 'mode', 'deployment status');

        return new self(
            id: Data::string($data, 'id', 'deployment status'),
            status: Data::enum($data, 'status', DeploymentState::class, 'deployment status'),
            url: Data::optionalString($data, 'url', 'deployment status') ?? '',
            desiredReplicas: Data::int($data, 'desiredReplicas', 'deployment status'),
            availableReplicas: Data::int($data, 'availableReplicas', 'deployment status'),
            revisions: Data::strings($data, 'revisions', 'deployment status'),
            traffic: \array_map(TrafficTarget::fromArray(...), Data::objects($data, 'traffic', 'deployment status')),
            mode: $mode === null ? null : TrafficMode::tryFrom($mode),
            error: Data::optionalString($data, 'error', 'deployment status'),
        );
    }
}
