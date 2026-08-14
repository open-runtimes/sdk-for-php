<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

use OpenRuntimes\Orchestrator\Enum\ActivationState;

/**
 * A warm pod claimed and late-bound to your payload, serving HTTP on its own
 * host.
 */
final readonly class Activation
{
    /**
     * @param  string|null  $id  Absent on an accepted async activation, which has not been assigned one yet.
     */
    public function __construct(
        public string $poolId,
        public ActivationState $status,
        public ?string $id = null,
        public ?string $url = null,
        public ?string $podId = null,
        public ?string $error = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            poolId: Data::string($data, 'poolId', 'activation'),
            status: Data::enum($data, 'status', ActivationState::class, 'activation'),
            id: Data::optionalString($data, 'id', 'activation'),
            url: Data::optionalString($data, 'url', 'activation'),
            podId: Data::optionalString($data, 'podId', 'activation'),
            error: Data::optionalString($data, 'error', 'activation'),
        );
    }
}
