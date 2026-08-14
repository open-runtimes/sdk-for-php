<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

final readonly class PoolList
{
    /**
     * @param  list<Pool>  $pools
     */
    public function __construct(public array $pools) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(\array_map(Pool::fromArray(...), Data::objects($data, 'pools', 'pool list')));
    }
}
