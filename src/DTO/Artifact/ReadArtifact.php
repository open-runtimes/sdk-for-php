<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\DTO\Artifact;

final readonly class ReadArtifact implements Artifact
{
    use ArtifactFields;

    public function __construct(
        public string $id,
        public string $in,
        public ?string $depends = null,
    ) {}

    public function type(): string
    {
        return 'read';
    }

    public function toArray(): array
    {
        return $this->base($this->type(), $this->id, $this->depends) + [
            'in' => $this->in,
        ];
    }
}
