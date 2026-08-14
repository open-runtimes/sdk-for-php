<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

/**
 * Concurrency-driven autoscaling for a deployment. `minReplicas: 0` enables
 * scale-to-zero, where the next request cold-starts the deployment.
 */
final readonly class Autoscaling implements ArraySerializable
{
    public function __construct(
        public int $minReplicas = 0,
        public ?int $maxReplicas = null,
        public ?int $target = null,
    ) {}

    public function toArray(): array
    {
        $data = ['minReplicas' => $this->minReplicas];

        if ($this->maxReplicas !== null) {
            $data['maxReplicas'] = $this->maxReplicas;
        }

        if ($this->target !== null) {
            $data['target'] = $this->target;
        }

        return $data;
    }
}
