<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Models\Artifact;

trait ArtifactFields
{
    /**
     * @return array<string, mixed>
     */
    private function base(string $type, string $id, ?string $depends): array
    {
        $data = [
            'id' => $id,
            'type' => $type,
        ];

        if ($depends !== null && $depends !== '') {
            $data['depends'] = $depends;
        }

        return $data;
    }
}
