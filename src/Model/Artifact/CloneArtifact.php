<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model\Artifact;

use OpenRuntimes\Orchestrator\Enum\ArtifactType;

/**
 * Materializes the tree at a git ref — for repositories whose provider hands
 * out no archive URLs. The clone is shallow, single-ref, and tagless, and the
 * .git directory is removed after checkout.
 *
 * The URL must not carry credentials; pass an Authorization header instead,
 * so the token never rides a string that errors and logs echo verbatim.
 */
final readonly class CloneArtifact implements Artifact
{
    use ArtifactFields;

    /**
     * @param  string  $ref  Branch name, tag name, or full commit hash; empty means the remote's default branch
     * @param  string  $subdir  Keep only this subdirectory of the tree
     * @param  array<string, string>  $headers  Sent with every git request, e.g. Authorization
     */
    public function __construct(
        public string $id,
        public string $in,
        public string $out,
        public string $ref = '',
        public string $subdir = '',
        public ?string $depends = null,
        public ?int $timeoutSeconds = null,
        public array $headers = [],
    ) {}

    public function type(): ArtifactType
    {
        return ArtifactType::Clone;
    }

    public function toArray(): array
    {
        $data = $this->base($this->type(), $this->id, $this->depends) + [
            'in' => $this->in,
            'out' => $this->out,
        ];

        if ($this->ref !== '') {
            $data['ref'] = $this->ref;
        }

        if ($this->subdir !== '') {
            $data['subdir'] = $this->subdir;
        }

        if ($this->timeoutSeconds !== null) {
            $data['timeoutSeconds'] = $this->timeoutSeconds;
        }

        if ($this->headers !== []) {
            $data['headers'] = $this->headers;
        }

        return $data;
    }
}
