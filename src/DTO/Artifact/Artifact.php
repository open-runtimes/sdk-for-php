<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\DTO\Artifact;

use OpenRuntimes\Orchestrator\DTO\ArraySerializable;

interface Artifact extends ArraySerializable
{
    public function type(): string;
}
