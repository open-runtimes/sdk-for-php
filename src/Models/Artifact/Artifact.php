<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Models\Artifact;

use OpenRuntimes\Orchestrator\Models\ArraySerializable;

interface Artifact extends ArraySerializable
{
    public function type(): string;
}
