<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

final readonly class ActivationList
{
    /**
     * @param  list<Activation>  $activations
     */
    public function __construct(public array $activations) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(\array_map(
            Activation::fromArray(...),
            Data::objects($data, 'activations', 'activation list'),
        ));
    }
}
