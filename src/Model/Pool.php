<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

/**
 * A configured warm pool. Pools are operator configuration rather than an API
 * resource, so the SDK reads them but never creates them.
 */
final readonly class Pool
{
    /**
     * @param  int  $size  Warm pods the pool is configured to keep ready.
     * @param  int  $warm  Unclaimed pods free to claim right now; always 0 on the Docker backend.
     * @param  int  $claimed  Pods bound to a live activation or sandbox.
     */
    public function __construct(
        public string $id,
        public string $image,
        public int $size = 0,
        public int $warm = 0,
        public int $claimed = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Data::string($data, 'id', 'pool'),
            image: Data::string($data, 'image', 'pool'),
            size: Data::int($data, 'size', 'pool'),
            warm: Data::int($data, 'warm', 'pool'),
            claimed: Data::int($data, 'claimed', 'pool'),
        );
    }
}
