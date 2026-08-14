<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

/**
 * A health probe. Omitting `path` makes it a TCP connect check rather than an
 * HTTP GET.
 */
final readonly class Probe implements ArraySerializable
{
    public function __construct(
        public ?string $path = null,
        public ?int $periodMillis = null,
        public ?int $timeoutMillis = null,
        public ?int $failureThreshold = null,
    ) {}

    public function toArray(): array
    {
        $data = [];

        if ($this->path !== null && $this->path !== '') {
            $data['path'] = $this->path;
        }

        if ($this->periodMillis !== null) {
            $data['periodMillis'] = $this->periodMillis;
        }

        if ($this->timeoutMillis !== null) {
            $data['timeoutMillis'] = $this->timeoutMillis;
        }

        if ($this->failureThreshold !== null) {
            $data['failureThreshold'] = $this->failureThreshold;
        }

        return $data;
    }
}
