<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

/**
 * A deployment's revisions, newest first, alongside the traffic table currently
 * in force across them.
 */
final readonly class RevisionList
{
    /**
     * @param  list<string>  $revisions
     * @param  list<TrafficTarget>  $traffic
     */
    public function __construct(
        public array $revisions,
        public array $traffic = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            revisions: Data::strings($data, 'revisions', 'revision list'),
            traffic: \array_map(TrafficTarget::fromArray(...), Data::objects($data, 'traffic', 'revision list')),
        );
    }
}
