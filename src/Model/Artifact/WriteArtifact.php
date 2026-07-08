<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model\Artifact;

use OpenRuntimes\Orchestrator\Enum\ArtifactType;

final readonly class WriteArtifact implements Artifact
{
    use ArtifactFields;

    public function __construct(
        public string $id,
        public string $in,
        public string $out,
        public ?string $depends = null,
    ) {}

    public function type(): ArtifactType
    {
        return ArtifactType::Write;
    }

    public function toArray(): array
    {
        return $this->base($this->type(), $this->id, $this->depends) + [
            'in' => $this->in,
            'out' => $this->out,
        ];
    }
}
