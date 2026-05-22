<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\DTO;

interface ArraySerializable
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
