<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator;

use OpenRuntimes\Orchestrator\Enum\RuntimeClass;
use OpenRuntimes\Orchestrator\Model\Artifact\Artifact;
use OpenRuntimes\Orchestrator\Model\SandboxList;
use OpenRuntimes\Orchestrator\Model\SandboxStatus;
use OpenRuntimes\Orchestrator\Model\Volume;
use Psr\Http\Client\ClientInterface;
use Utopia\Client\Adapter\Curl\Client as CurlAdapter;
use Utopia\Client\Client as HttpClient;
use Utopia\Psr7\Method;

/**
 * Sandboxes: live, isolated workspaces you create, inspect, and tear down.
 *
 * Running commands and moving files are **not** part of this API — they are an
 * HTTP contract served inside the sandbox itself, at the address returned as
 * `url`. This client never sits between you and your commands.
 */
final readonly class Sandboxes
{
    private Transport $transport;

    public function __construct(
        ClientInterface $client = new HttpClient(new CurlAdapter),
    ) {
        $this->transport = new Transport($client);
    }

    /**
     * Create a sandbox, returning once it is ready or failed.
     *
     * The complete requested pod shape is matched transparently against warm
     * capacity. A matching pool improves startup latency; it never changes the
     * requested sandbox or whether a valid create is accepted.
     *
     * @param  int  $port  Where the contract is served.
     * @param  list<int>  $ports  Extra ports to expose, each at its own hostname.
     * @param  array<string, string>  $environment
     * @param  list<Artifact>  $artifacts  Materialized into the workspace before the sandbox reports ready.
     * @param  list<Volume>  $volumes
     * @param  int|null  $timeoutSeconds  Bounds each request to the sandbox; 0 removes the bound, which
     *                                    long-lived sessions such as terminals and LSP need.
     * @param  int|null  $idleTimeoutSeconds  Tear down after this long with no traffic; 0 = until delete().
     */
    public function create(
        string $image,
        int $port,
        ?string $id = null,
        ?string $command = null,
        array $environment = [],
        array $ports = [],
        array $artifacts = [],
        array $volumes = [],
        ?float $cpu = null,
        ?int $memory = null,
        ?RuntimeClass $runtimeClass = null,
        ?int $timeoutSeconds = null,
        ?int $idleTimeoutSeconds = null,
        ?int $terminationGracePeriodSeconds = null,
    ): SandboxStatus {
        $payload = [
            'image' => $image,
            'port' => $port,
        ];

        if ($id !== null && $id !== '') {
            $payload['id'] = $id;
        }

        if ($command !== null && $command !== '') {
            $payload['command'] = $command;
        }

        if ($environment !== []) {
            $payload['environment'] = $environment;
        }

        if ($ports !== []) {
            $payload['ports'] = $ports;
        }

        if ($artifacts !== []) {
            $payload['artifacts'] = \array_map(static fn (Artifact $artifact): array => $artifact->toArray(), $artifacts);
        }

        if ($volumes !== []) {
            $payload['volumes'] = \array_map(static fn (Volume $volume): array => $volume->toArray(), $volumes);
        }

        if ($cpu !== null) {
            $payload['cpu'] = $cpu;
        }

        if ($memory !== null) {
            $payload['memory'] = $memory;
        }

        if ($runtimeClass instanceof RuntimeClass) {
            $payload['runtimeClass'] = $runtimeClass->value;
        }

        if ($timeoutSeconds !== null) {
            $payload['timeoutSeconds'] = $timeoutSeconds;
        }

        if ($idleTimeoutSeconds !== null) {
            $payload['idleTimeoutSeconds'] = $idleTimeoutSeconds;
        }

        if ($terminationGracePeriodSeconds !== null) {
            $payload['terminationGracePeriodSeconds'] = $terminationGracePeriodSeconds;
        }

        return SandboxStatus::fromArray($this->transport->json(Method::POST, '/v1/sandbox', $payload));
    }

    public function get(string $sandboxId): SandboxStatus
    {
        return SandboxStatus::fromArray($this->transport->json(Method::GET, '/v1/sandbox/'.\rawurlencode($sandboxId)));
    }

    public function list(): SandboxList
    {
        return SandboxList::fromArray($this->transport->json(Method::GET, '/v1/sandbox'));
    }

    /**
     * Tear a sandbox down. This invalidates its URL immediately, before the pod
     * has finished terminating.
     */
    public function delete(string $sandboxId): void
    {
        $this->transport->discard(Method::DELETE, '/v1/sandbox/'.\rawurlencode($sandboxId));
    }
}
