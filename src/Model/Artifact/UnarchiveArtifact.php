<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model\Artifact;

use OpenRuntimes\Orchestrator\Enum\ArtifactType;

final readonly class UnarchiveArtifact implements Artifact
{
    use ArtifactFields;

    public function __construct(
        public string $id,
        public string $in,
        public string $out,
        public ?string $subdir = null,
        public bool $strip = false,
        public ?string $depends = null,
    ) {}

    public function type(): ArtifactType
    {
        return ArtifactType::Unarchive;
    }

    public function toArray(): array
    {
        $data = $this->base($this->type(), $this->id, $this->depends) + [
            'in' => $this->in,
            'out' => $this->out,
        ];

        if ($this->subdir !== null && $this->subdir !== '') {
            $data['subdir'] = $this->subdir;
        }

        if ($this->strip) {
            $data['strip'] = true;
        }

        return $data;
    }
}
