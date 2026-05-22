<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator;

use OpenRuntimes\Orchestrator\Exception\OrchestratorException;
use OpenRuntimes\Orchestrator\Service\Jobs;
use Utopia\Fetch\Adapter;

final readonly class Client
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        private string $endpoint,
        private ?string $apiKey = null,
        private int $timeoutSeconds = 30,
        private array $headers = [],
        private ?Adapter $adapter = null,
    ) {
        if ($this->normalizedEndpoint() === '') {
            throw new OrchestratorException('Orchestrator endpoint is required.');
        }
    }

    public function jobs(): Jobs
    {
        return new Jobs(
            endpoint: $this->normalizedEndpoint(),
            apiKey: $this->apiKey,
            timeoutSeconds: $this->timeoutSeconds,
            headers: $this->headers,
            adapter: $this->adapter,
        );
    }

    private function normalizedEndpoint(): string
    {
        return \rtrim($this->endpoint, '/');
    }
}
