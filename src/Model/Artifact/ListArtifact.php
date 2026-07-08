<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model\Artifact;

final readonly class ListArtifact implements Artifact
{
    use ArtifactFields;

    /**
     * @param  list<string>  $excludes
     */
    public function __construct(
        public string $id,
        public string $in,
        public ?bool $recursive = null,
        public array $excludes = [],
        public ?string $depends = null,
    ) {}

    public function type(): string
    {
        return 'list';
    }

    public function toArray(): array
    {
        $data = $this->base($this->type(), $this->id, $this->depends) + [
            'in' => $this->in,
        ];

        if ($this->recursive !== null) {
            $data['recursive'] = $this->recursive;
        }

        if ($this->excludes !== []) {
            $data['excludes'] = $this->excludes;
        }

        return $data;
    }
}
