<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

final readonly class DeploymentList
{
    /**
     * @param  list<DeploymentStatus>  $deployments
     */
    public function __construct(public array $deployments) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(\array_map(
            DeploymentStatus::fromArray(...),
            Data::objects($data, 'deployments', 'deployment list'),
        ));
    }
}
