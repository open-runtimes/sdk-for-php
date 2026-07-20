<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model\Artifact;

use OpenRuntimes\Orchestrator\Enum\ArchiveCompression;
use OpenRuntimes\Orchestrator\Enum\ArchiveFormat;
use OpenRuntimes\Orchestrator\Enum\ArtifactType;

final readonly class ArchiveArtifact implements Artifact
{
    use ArtifactFields;

    public function __construct(
        public string $id,
        public string $in,
        public string $out,
        public ArchiveFormat $format = ArchiveFormat::Tar,
        public ?ArchiveCompression $compression = null,
        public ?int $level = null,
        public ?int $blockSize = null,
        public ?string $depends = null,
    ) {}

    public function type(): ArtifactType
    {
        return ArtifactType::Archive;
    }

    public function toArray(): array
    {
        $data = $this->base($this->type(), $this->id, $this->depends) + [
            'in' => $this->in,
            'out' => $this->out,
            'format' => $this->format->value,
        ];

        if ($this->compression instanceof ArchiveCompression) {
            $data['compression'] = $this->compression->value;
        }

        if ($this->level !== null) {
            $data['level'] = $this->level;
        }

        if ($this->blockSize !== null) {
            $data['blockSize'] = $this->blockSize;
        }

        return $data;
    }
}
