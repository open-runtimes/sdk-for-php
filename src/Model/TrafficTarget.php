<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

/**
 * One leg of a deployment's traffic split. Percents across a split must sum
 * to 100.
 */
final readonly class TrafficTarget implements ArraySerializable
{
    public function __construct(
        public string $revisionName,
        public int $percent,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            revisionName: Data::string($data, 'revisionName', 'traffic target'),
            percent: Data::int($data, 'percent', 'traffic target'),
        );
    }

    public function toArray(): array
    {
        return [
            'revisionName' => $this->revisionName,
            'percent' => $this->percent,
        ];
    }
}
