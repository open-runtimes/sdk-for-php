<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

interface ArraySerializable
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
