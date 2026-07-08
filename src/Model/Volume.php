<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

final readonly class Volume implements ArraySerializable
{
    public function __construct(
        public string $source,
        public string $path,
        public ?string $subPath = null,
        public bool $readonly = false,
    ) {}

    public function toArray(): array
    {
        $data = [
            'source' => $this->source,
            'path' => $this->path,
        ];

        if ($this->subPath !== null && $this->subPath !== '') {
            $data['subPath'] = $this->subPath;
        }

        if ($this->readonly) {
            $data['readonly'] = true;
        }

        return $data;
    }
}
