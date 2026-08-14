<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

final readonly class SandboxList
{
    /**
     * @param  list<SandboxStatus>  $sandboxes
     */
    public function __construct(public array $sandboxes) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(\array_map(
            SandboxStatus::fromArray(...),
            Data::objects($data, 'sandboxes', 'sandbox list'),
        ));
    }
}
