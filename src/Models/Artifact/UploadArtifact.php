<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Models\Artifact;

final readonly class UploadArtifact implements Artifact
{
    use ArtifactFields;

    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $id,
        public string $in,
        public string $out,
        public ?string $depends = null,
        public ?int $timeoutSeconds = null,
        public ?int $retries = null,
        public array $headers = [],
        public bool $chunked = false,
    ) {}

    public function type(): string
    {
        return 'upload';
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

        if ($this->chunked) {
            $data['chunked'] = true;
        }

        return $data;
    }
}
