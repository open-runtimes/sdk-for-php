<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator;

use OpenRuntimes\Orchestrator\Enum\RuntimeClass;
use OpenRuntimes\Orchestrator\Model\Artifact\Artifact;
use OpenRuntimes\Orchestrator\Model\Autoscaling;
use OpenRuntimes\Orchestrator\Model\Callback;
use OpenRuntimes\Orchestrator\Model\DeploymentList;
use OpenRuntimes\Orchestrator\Model\DeploymentStatus;
use OpenRuntimes\Orchestrator\Model\Probes;
use OpenRuntimes\Orchestrator\Model\RevisionList;
use OpenRuntimes\Orchestrator\Model\TrafficTarget;
use OpenRuntimes\Orchestrator\Model\Volume;
use Psr\Http\Client\ClientInterface;
use Utopia\Client\Adapter\Curl\Client as CurlAdapter;
use Utopia\Client as HttpClient;
use Utopia\Psr7\Method;

/**
 * Deployments: containers serving HTTP behind the orchestrator's gateway, kept
 * running, routable, and scaled — including down to zero.
 */
final readonly class Deployments
{
    private Transport $transport;

    public function __construct(
        ClientInterface $client = new HttpClient(new CurlAdapter),
    ) {
        $this->transport = new Transport($client);
    }

    /**
     * Declaratively create or update a deployment.
     *
     * Applying a changed spec for an existing id rolls out a new revision, which
     * takes traffic once it reports ready; re-applying an identical spec is a
     * no-op.
     *
     * @param  int  $port  The container port serving HTTP.
     * @param  list<string>  $hosts  `hosts[0]` is the primary; defaults to `{id}.{domain}`.
     * @param  array<string, string>  $meta
     * @param  array<string, string>  $environment
     * @param  list<Artifact>  $artifacts
     * @param  list<Volume>  $volumes
     * @param  int|null  $concurrency  Hard per-replica in-flight cap; 0 = unlimited.
     */
    public function apply(
        string $id,
        string $image,
        int $port,
        ?string $command = null,
        float $cpu = 1.0,
        int $memory = 512,
        string $workspace = '/workspace',
        array $hosts = [],
        array $meta = [],
        array $environment = [],
        array $artifacts = [],
        array $volumes = [],
        ?int $replicas = null,
        ?int $concurrency = null,
        ?Autoscaling $autoscaling = null,
        ?Probes $probes = null,
        ?Callback $callback = null,
        ?RuntimeClass $runtimeClass = null,
        ?int $timeoutSeconds = null,
        ?int $startTimeoutSeconds = null,
        ?int $readyTimeoutSeconds = null,
    ): DeploymentStatus {
        $payload = [
            'id' => $id,
            'image' => $image,
            'port' => $port,
            'cpu' => $cpu,
            'memory' => $memory,
            'workspace' => $workspace,
        ];

        if ($command !== null && $command !== '') {
            $payload['command'] = $command;
        }

        if ($hosts !== []) {
            $payload['hosts'] = $hosts;
        }

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

        if ($replicas !== null) {
            $payload['replicas'] = $replicas;
        }

        if ($concurrency !== null) {
            $payload['concurrency'] = $concurrency;
        }

        if ($autoscaling instanceof Autoscaling) {
            $payload['autoscaling'] = $autoscaling->toArray();
        }

        if ($probes instanceof Probes) {
            $payload['probes'] = $probes->toArray();
        }

        if ($callback instanceof Callback) {
            $payload['callback'] = $callback->toArray();
        }

        if ($runtimeClass instanceof RuntimeClass) {
            $payload['runtimeClass'] = $runtimeClass->value;
        }

        if ($timeoutSeconds !== null) {
            $payload['timeoutSeconds'] = $timeoutSeconds;
        }

        if ($startTimeoutSeconds !== null) {
            $payload['startTimeoutSeconds'] = $startTimeoutSeconds;
        }

        if ($readyTimeoutSeconds !== null) {
            $payload['readyTimeoutSeconds'] = $readyTimeoutSeconds;
        }

        return DeploymentStatus::fromArray($this->transport->json(Method::POST, '/v1/deployments', $payload));
    }

    public function get(string $deploymentId): DeploymentStatus
    {
        return DeploymentStatus::fromArray($this->transport->json(Method::GET, $this->path($deploymentId)));
    }

    public function list(): DeploymentList
    {
        return DeploymentList::fromArray($this->transport->json(Method::GET, '/v1/deployments'));
    }

    public function delete(string $deploymentId): void
    {
        $this->transport->discard(Method::DELETE, $this->path($deploymentId));
    }

    /**
     * List a deployment's revisions, newest first, with the traffic table
     * currently in force.
     */
    public function revisions(string $deploymentId): RevisionList
    {
        return RevisionList::fromArray($this->transport->json(Method::GET, $this->path($deploymentId).'/revisions'));
    }

    /**
     * Pin an explicit traffic split across existing revisions — a canary, a
     * blue-green cut, or a rollback. Percents must sum to 100.
     *
     * Setting a split switches the deployment to manual mode: new revisions
     * still roll out, but traffic stays where you put it until you release().
     *
     * @param  list<TrafficTarget>  $targets
     */
    public function setTraffic(string $deploymentId, array $targets): DeploymentStatus
    {
        return DeploymentStatus::fromArray($this->transport->json(
            Method::POST,
            $this->path($deploymentId).'/traffic',
            ['targets' => \array_map(static fn (TrafficTarget $target): array => $target->toArray(), $targets)],
        ));
    }

    /**
     * Release traffic back to auto mode: 100% on the latest revision, with
     * auto-cut on new revisions re-armed.
     */
    public function release(string $deploymentId): DeploymentStatus
    {
        return $this->setTraffic($deploymentId, []);
    }

    private function path(string $deploymentId): string
    {
        return '/v1/deployments/'.\rawurlencode($deploymentId);
    }
}
