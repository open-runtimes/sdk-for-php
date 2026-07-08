<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model\Artifact;

use OpenRuntimes\Orchestrator\Model\ArraySerializable;

interface Artifact extends ArraySerializable
{
    public function type(): string;
}
