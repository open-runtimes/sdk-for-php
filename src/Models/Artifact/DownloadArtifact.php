<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Models\Artifact;

final readonly class DownloadArtifact implements Artifact
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
        public array $headers = [],
    ) {}

    public function type(): string
    {
        return 'download';
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

        if ($this->headers !== []) {
            $data['headers'] = $this->headers;
        }

        return $data;
    }
}
