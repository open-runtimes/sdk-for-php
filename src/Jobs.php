<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator;

use OpenRuntimes\Orchestrator\Model\Artifact\Artifact;
use OpenRuntimes\Orchestrator\Model\Callback;
use OpenRuntimes\Orchestrator\Model\JobCreated;
use OpenRuntimes\Orchestrator\Model\JobList;
use OpenRuntimes\Orchestrator\Model\JobStatus;
use OpenRuntimes\Orchestrator\Model\Volume;
use Psr\Http\Client\ClientInterface;
use Utopia\Client\Adapter\Curl\Client as CurlAdapter;
use Utopia\Client as HttpClient;
use Utopia\Psr7\Method;

final readonly class Jobs
{
    private Transport $transport;

    public function __construct(
        ClientInterface $client = new HttpClient(new CurlAdapter),
    ) {
        $this->transport = new Transport($client);
    }

    /**
     * @param  array<string, string>  $meta
     * @param  array<string, string>  $environment
     * @param  list<Artifact>  $artifacts
     * @param  list<Volume>  $volumes
     */
    public function create(
        string $id,
        string $image,
        string $command,
        float $cpu = 1.0,
        int $memory = 512,
        int $timeoutSeconds = 1800,
        string $workspace = '/workspace',
        array $meta = [],
        array $environment = [],
        array $artifacts = [],
        array $volumes = [],
        ?Callback $callback = null,
    ): JobCreated {
        $payload = [
            'id' => $id,
            'image' => $image,
            'command' => $command,
            'cpu' => $cpu,
            'memory' => $memory,
            'timeoutSeconds' => $timeoutSeconds,
            'workspace' => $workspace,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        if ($environment !== []) {
            $payload['environment'] = $environment;
        }

        if ($artifacts !== []) {
            $payload['artifacts'] = \array_map(static fn (Artifact $artifact): array => $artifact->toArray(), $artifacts);
        }

        if ($volumes !== []) {
            $payload['volumes'] = \array_map(static fn (Volume $volume): array => $volume->toArray(), $volumes);
        }

        if ($callback instanceof Callback) {
            $payload['callback'] = $callback->toArray();
        }

        return JobCreated::fromArray($this->transport->json(Method::POST, '/v1/jobs', $payload));
    }

    public function get(string $jobId): JobStatus
    {
        return JobStatus::fromArray($this->transport->json(Method::GET, '/v1/jobs/'.\rawurlencode($jobId)));
    }

    public function list(): JobList
    {
        return JobList::fromArray($this->transport->json(Method::GET, '/v1/jobs'));
    }

    public function delete(string $jobId): void
    {
        $this->transport->discard(Method::DELETE, '/v1/jobs/'.\rawurlencode($jobId));
    }
}
