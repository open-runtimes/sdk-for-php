<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model\Artifact;

use OpenRuntimes\Orchestrator\Enum\ArtifactType;

trait ArtifactFields
{
    /**
     * @return array<string, mixed>
     */
    private function base(ArtifactType $type, string $id, ?string $depends): array
    {
        $data = [
            'id' => $id,
            'type' => $type->value,
        ];

        if ($depends !== null && $depends !== '') {
            $data['depends'] = $depends;
        }

        return $data;
    }
}
