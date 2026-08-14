<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator;

use OpenRuntimes\Orchestrator\Exception\ClientException;
use OpenRuntimes\Orchestrator\Model\Activation;
use OpenRuntimes\Orchestrator\Model\ActivationList;
use OpenRuntimes\Orchestrator\Model\Artifact\Artifact;
use OpenRuntimes\Orchestrator\Model\Callback;
use OpenRuntimes\Orchestrator\Model\Pool;
use OpenRuntimes\Orchestrator\Model\PoolList;
use Psr\Http\Client\ClientInterface;
use Utopia\Client\Adapter\Curl\Client as CurlAdapter;
use Utopia\Client as HttpClient;
use Utopia\Psr7\Method;

/**
 * Deployment pools: standing warm capacity, and the activations that claim a
 * warm pod and late-bind a payload onto it. Pools themselves are operator
 * configuration, so the API over them is read plus activate.
 */
final readonly class DeploymentPools
{
    private Transport $transport;

    public function __construct(
        ClientInterface $client = new HttpClient(new CurlAdapter),
    ) {
        $this->transport = new Transport($client);
    }

    public function list(): PoolList
    {
        return PoolList::fromArray($this->transport->json(Method::GET, '/v1/deployment-pools'));
    }

    public function get(string $poolId): Pool
    {
        return Pool::fromArray($this->transport->json(Method::GET, $this->path($poolId)));
    }

    /**
     * Claim a warm pod and run a command on it, returning once the workload is
     * serving on its URL.
     *
     * Set `async` to get an accepted activation back immediately instead; the
     * result then arrives at your callback as an
     * `orchestrator.pool.activation.result` event, which is why async requires
     * one — nothing is stored to poll in the meantime.
     *
     * @param  string|null  $id  Choosing one buys idempotency: re-activating a live id is a 409.
     * @param  string|null  $host  Defaults to `{id}.{pool domain}`.
     * @param  array<string, string>  $environment
     * @param  list<Artifact>  $artifacts
     * @param  int|null  $idleTimeoutSeconds  Tear down after this long with no traffic; 0 = until deactivate().
     */
    public function activate(
        string $poolId,
        string $command,
        ?string $id = null,
        ?string $host = null,
        array $environment = [],
        array $artifacts = [],
        ?int $timeoutSeconds = null,
        ?int $idleTimeoutSeconds = null,
        ?Callback $callback = null,
        bool $async = false,
    ): Activation {
        if ($async && ! $callback instanceof Callback) {
            throw new ClientException('An async activation requires a callback to deliver its result to.');
        }

        $payload = ['command' => $command];

        if ($id !== null && $id !== '') {
            $payload['id'] = $id;
        }

        if ($host !== null && $host !== '') {
            $payload['host'] = $host;
        }

        if ($environment !== []) {
            $payload['environment'] = $environment;
        }

        if ($artifacts !== []) {
            $payload['artifacts'] = \array_map(static fn (Artifact $artifact): array => $artifact->toArray(), $artifacts);
        }

        if ($timeoutSeconds !== null) {
            $payload['timeoutSeconds'] = $timeoutSeconds;
        }

        if ($idleTimeoutSeconds !== null) {
            $payload['idleTimeoutSeconds'] = $idleTimeoutSeconds;
        }

        if ($callback instanceof Callback) {
            $payload['callback'] = $callback->toArray();
        }

        return Activation::fromArray($this->transport->json(
            Method::POST,
            $this->activationsPath($poolId),
            $payload,
            $async ? ['Prefer' => 'respond-async'] : [],
        ));
    }

    public function activations(string $poolId): ActivationList
    {
        return ActivationList::fromArray($this->transport->json(Method::GET, $this->activationsPath($poolId)));
    }

    public function activation(string $poolId, string $activationId): Activation
    {
        return Activation::fromArray($this->transport->json(Method::GET, $this->activationPath($poolId, $activationId)));
    }

    /**
     * Tear an activation down. The pod is discarded rather than reused, and the
     * pool replenishes with a fresh one.
     */
    public function deactivate(string $poolId, string $activationId): void
    {
        $this->transport->discard(Method::DELETE, $this->activationPath($poolId, $activationId));
    }

    private function path(string $poolId): string
    {
        return '/v1/deployment-pools/'.\rawurlencode($poolId);
    }

    private function activationsPath(string $poolId): string
    {
        return $this->path($poolId).'/activations';
    }

    private function activationPath(string $poolId, string $activationId): string
    {
        return $this->activationsPath($poolId).'/'.\rawurlencode($activationId);
    }
}
