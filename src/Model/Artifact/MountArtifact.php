<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model\Artifact;

use OpenRuntimes\Orchestrator\Enum\ArtifactType;

final readonly class MountArtifact implements Artifact
{
    use ArtifactFields;

    public function __construct(
        public string $id,
        public string $in,
        public string $out,
        public bool $writable = false,
        public ?int $size = null,
        public ?string $depends = null,
    ) {}

    public function type(): ArtifactType
    {
        return ArtifactType::Mount;
    }

    public function toArray(): array
    {
        $data = $this->base($this->type(), $this->id, $this->depends) + [
            'in' => $this->in,
            'out' => $this->out,
        ];

        if ($this->writable) {
            $data['writable'] = true;
        }

        if ($this->size !== null) {
            $data['size'] = $this->size;
        }

        return $data;
    }
}
