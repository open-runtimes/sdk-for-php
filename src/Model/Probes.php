<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

/**
 * A deployment's probe set. The readiness probe is run by the orchestrator's
 * sidecar and gates traffic, honouring sub-second periods; liveness and startup
 * probes are kubelet-run at whole-second granularity.
 */
final readonly class Probes implements ArraySerializable
{
    public function __construct(
        public ?Probe $readiness = null,
        public ?Probe $liveness = null,
        public ?Probe $startup = null,
    ) {}

    public function toArray(): array
    {
        $data = [];

        if ($this->readiness instanceof Probe) {
            $data['readiness'] = $this->readiness->toArray();
        }

        if ($this->liveness instanceof Probe) {
            $data['liveness'] = $this->liveness->toArray();
        }

        if ($this->startup instanceof Probe) {
            $data['startup'] = $this->startup->toArray();
        }

        return $data;
    }
}
