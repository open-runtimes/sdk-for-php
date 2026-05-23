<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\DTO;

use OpenRuntimes\Orchestrator\DTO\Artifact\Artifact;

final readonly class JobRequest implements ArraySerializable
{
    /**
     * @param  array<string, string>  $meta
     * @param  array<string, string>  $environment
     * @param  list<Artifact>  $artifacts
     */
    public function __construct(
        public string $id,
        public string $image,
        public string $command,
        public float $cpu = 1.0,
        public int $memory = 512,
        public int $timeoutSeconds = 1800,
        public string $workspace = '/workspace',
        public array $meta = [],
        public array $environment = [],
        public array $artifacts = [],
        public ?Callback $callback = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'image' => $this->image,
            'command' => $this->command,
            'cpu' => $this->cpu,
            'memory' => $this->memory,
            'timeoutSeconds' => $this->timeoutSeconds,
            'workspace' => $this->workspace,
        ];

        if ($this->meta !== []) {
            $data['meta'] = $this->meta;
        }

        if ($this->environment !== []) {
            $data['environment'] = $this->environment;
        }

        if ($this->artifacts !== []) {
            $data['artifacts'] = \array_map(static fn (Artifact $artifact): array => $artifact->toArray(), $this->artifacts);
        }

        if ($this->callback instanceof Callback) {
            $data['callback'] = $this->callback->toArray();
        }

        return $data;
    }
}
