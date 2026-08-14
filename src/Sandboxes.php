<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator;

use OpenRuntimes\Orchestrator\Enum\RuntimeClass;
use OpenRuntimes\Orchestrator\Exception\ClientException;
use OpenRuntimes\Orchestrator\Model\Artifact\Artifact;
use OpenRuntimes\Orchestrator\Model\Pool;
use OpenRuntimes\Orchestrator\Model\PoolList;
use OpenRuntimes\Orchestrator\Model\SandboxList;
use OpenRuntimes\Orchestrator\Model\SandboxStatus;
use OpenRuntimes\Orchestrator\Model\Volume;
use Psr\Http\Client\ClientInterface;
use Utopia\Client\Adapter\Curl\Client as CurlAdapter;
use Utopia\Client as HttpClient;
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
     * Pass exactly one of `pool` or `image`. Naming a pool claims an already
     * running pod, so the create is sub-second; naming an image builds a pod for
     * this request instead — no standing capacity to configure, at the cost of a
     * cold start, and `cpu`, `memory`, `runtimeClass` and `volumes` become
     * yours to set rather than the pool's.
     *
     * @param  int|null  $port  Where the contract is served. Required with `image`.
     * @param  list<int>  $ports  Extra ports to expose, each at its own hostname.
     * @param  array<string, string>  $environment
     * @param  list<Artifact>  $artifacts  Materialized into the workspace before the sandbox reports ready.
     * @param  list<Volume>  $volumes  Poolless sandboxes only; on a pool, volumes are a pool dimension.
     * @param  int|null  $timeoutSeconds  Bounds each request to the sandbox; 0 removes the bound, which
     *                                    long-lived sessions such as terminals and LSP need.
     * @param  int|null  $idleTimeoutSeconds  Tear down after this long with no traffic; 0 = until delete().
     */
    public function create(
        ?string $pool = null,
        ?string $image = null,
        ?int $port = null,
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
    ): SandboxStatus {
        if (($pool === null) === ($image === null)) {
            throw new ClientException('Creating a sandbox takes exactly one of pool or image.');
        }

        if ($image !== null && $port === null) {
            throw new ClientException('Creating a sandbox from an image requires a port.');
        }

        $payload = [];

        if ($pool !== null) {
            $payload['pool'] = $pool;
        }

        if ($image !== null) {
            $payload['image'] = $image;
        }

        if ($port !== null) {
            $payload['port'] = $port;
        }

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

    /**
     * List the configured sandbox pools with their live warm and claimed counts.
     */
    public function pools(): PoolList
    {
        return PoolList::fromArray($this->transport->json(Method::GET, '/v1/sandbox-pool'));
    }

    public function pool(string $poolId): Pool
    {
        return Pool::fromArray($this->transport->json(Method::GET, '/v1/sandbox-pool/'.\rawurlencode($poolId)));
    }
}
