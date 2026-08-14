<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

use OpenRuntimes\Orchestrator\Enum\SandboxState;

/**
 * A sandbox's status. Treat `url` and `urls` as secrets: reaching a sandbox's
 * address is sufficient to run commands in it, so the hostname carries an
 * unguessable token rather than the id.
 */
final readonly class SandboxStatus
{
    /**
     * @param  array<string, string>  $urls  Every port the sandbox serves, keyed by port number.
     */
    public function __construct(
        public string $id,
        public SandboxState $status,
        public ?string $poolId = null,
        public ?string $url = null,
        public array $urls = [],
        public ?string $error = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Data::string($data, 'id', 'sandbox status'),
            status: Data::enum($data, 'status', SandboxState::class, 'sandbox status'),
            poolId: Data::optionalString($data, 'poolId', 'sandbox status'),
            url: Data::optionalString($data, 'url', 'sandbox status'),
            urls: Data::stringMap($data, 'urls', 'sandbox status'),
            error: Data::optionalString($data, 'error', 'sandbox status'),
        );
    }
}
