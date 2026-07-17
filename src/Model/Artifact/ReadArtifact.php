<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model\Artifact;

use OpenRuntimes\Orchestrator\Enum\ArtifactType;
use OpenRuntimes\Orchestrator\Enum\ReadFormat;

final readonly class ReadArtifact implements Artifact
{
    use ArtifactFields;

    public function __construct(
        public string $id,
        public string $in,
        public ?ReadFormat $format = null,
        public ?string $depends = null,
    ) {}

    public function type(): ArtifactType
    {
        return ArtifactType::Read;
    }

    public function toArray(): array
    {
        $data = $this->base($this->type(), $this->id, $this->depends) + [
            'in' => $this->in,
        ];

        if ($this->format instanceof ReadFormat) {
            $data['format'] = $this->format->value;
        }

        return $data;
    }
}
