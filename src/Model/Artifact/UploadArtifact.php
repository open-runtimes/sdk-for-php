<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model\Artifact;

use OpenRuntimes\Orchestrator\Enum\ArtifactType;

final readonly class UploadArtifact implements Artifact
{
    use ArtifactFields;

    /**
     * An `out` ending in `/` uploads the directory `in` file by file under it,
     * skipping any entry whose name matches a glob in `excludes`.
     *
     * @param  array<string, string>  $headers
     * @param  list<string>  $excludes
     */
    public function __construct(
        public string $id,
        public string $in,
        public string $out,
        public ?string $depends = null,
        public ?int $timeoutSeconds = null,
        public ?int $retries = null,
        public array $headers = [],
        public array $excludes = [],
    ) {}

    public function type(): ArtifactType
    {
        return ArtifactType::Upload;
    }

    public function toArray(): array
    {
        $data = $this->base($this->type(), $this->id, $this->depends) + [
            'in' => $this->in,
            'out' => $this->out,
        ];

        if ($this->timeoutSeconds !== null) {
            $data['timeoutSeconds'] = $this->timeoutSeconds;
        }

        if ($this->retries !== null) {
            $data['retries'] = $this->retries;
        }

        if ($this->headers !== []) {
            $data['headers'] = $this->headers;
        }

        if ($this->excludes !== []) {
            $data['excludes'] = $this->excludes;
        }

        return $data;
    }
}
