<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Tests;

use OpenRuntimes\Orchestrator\Deployments;
use OpenRuntimes\Orchestrator\Enum\DeploymentState;
use OpenRuntimes\Orchestrator\Enum\RuntimeClass;
use OpenRuntimes\Orchestrator\Enum\TrafficMode;
use OpenRuntimes\Orchestrator\Exception\ApiException;
use OpenRuntimes\Orchestrator\Model\Artifact\DownloadArtifact;
use OpenRuntimes\Orchestrator\Model\Autoscaling;
use OpenRuntimes\Orchestrator\Model\Callback;
use OpenRuntimes\Orchestrator\Model\Probe;
use OpenRuntimes\Orchestrator\Model\Probes;
use OpenRuntimes\Orchestrator\Model\TrafficTarget;
use PHPUnit\Framework\TestCase;
use Utopia\Psr7\Response;
use Utopia\Psr7\Stream;

final class DeploymentsTest extends TestCase
{
    public function test_apply_sends_minimal_spec_and_hydrates_status(): void
    {
        $http = new Client([new Response(201, body: new Stream(
            '{"id":"web","status":"pending","url":"http://web.localhost","revisions":["web-00001"],'
            .'"traffic":[{"revisionName":"web-00001","percent":100}],"mode":"auto",'
            .'"desiredReplicas":1,"availableReplicas":0}'
        ))]);

        $status = new Deployments($http)->apply(id: 'web', image: 'traefik/whoami', port: 80);

        $this->assertSame('web', $status->id);
        $this->assertSame(DeploymentState::Pending, $status->status);
        $this->assertSame('http://web.localhost', $status->url);
        $this->assertSame(['web-00001'], $status->revisions);
        $this->assertSame(TrafficMode::Auto, $status->mode);
        $this->assertSame(1, $status->desiredReplicas);
        $this->assertSame(0, $status->availableReplicas);
        $this->assertCount(1, $status->traffic);
        $this->assertSame('web-00001', $status->traffic[0]->revisionName);
        $this->assertSame(100, $status->traffic[0]->percent);

        $request = $http->requests[0];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1/deployments', (string) $request->getUri());
        $this->assertJsonStringEqualsJsonString(
            '{"id":"web","image":"traefik/whoami","port":80,"cpu":1,"memory":512,"workspace":"/workspace"}',
            (string) $request->getBody(),
        );
    }

    public function test_apply_serializes_the_full_spec(): void
    {
        $http = new Client([new Response(200, body: new Stream('{"id":"api","status":"ready","url":"http://api.test"}'))]);

        new Deployments($http)->apply(
            id: 'api',
            image: 'ghcr.io/acme/api:v3',
            port: 8080,
            command: 'server --flag',
            cpu: 0.5,
            memory: 1024,
            hosts: ['acme.com', 'www.acme.com'],
            meta: ['team' => 'core'],
            environment: ['LOG' => 'debug'],
            artifacts: [new DownloadArtifact('cfg', 'https://acme.test/c.yaml', 'config.yaml')],
            replicas: 2,
            concurrency: 50,
            autoscaling: new Autoscaling(minReplicas: 0, maxReplicas: 10, target: 100),
            probes: new Probes(readiness: new Probe(path: '/healthz', periodMillis: 500)),
            callback: new Callback(url: 'https://acme.test/hook', key: 'secret'),
            runtimeClass: RuntimeClass::Gvisor,
            timeoutSeconds: 300,
            startTimeoutSeconds: 120,
            readyTimeoutSeconds: 600,
        );

        $this->assertJsonStringEqualsJsonString(
            '{"id":"api","image":"ghcr.io/acme/api:v3","port":8080,"cpu":0.5,"memory":1024,'
            .'"workspace":"/workspace","command":"server --flag","hosts":["acme.com","www.acme.com"],'
            .'"meta":{"team":"core"},"environment":{"LOG":"debug"},'
            .'"artifacts":[{"id":"cfg","type":"download","in":"https://acme.test/c.yaml","out":"config.yaml"}],'
            .'"replicas":2,"concurrency":50,"autoscaling":{"minReplicas":0,"maxReplicas":10,"target":100},'
            .'"probes":{"readiness":{"path":"/healthz","periodMillis":500}},'
            .'"callback":{"url":"https://acme.test/hook","events":[],"key":"secret"},'
            .'"runtimeClass":"gvisor","timeoutSeconds":300,"startTimeoutSeconds":120,"readyTimeoutSeconds":600}',
            (string) $http->requests[0]->getBody(),
        );
    }

    public function test_get_list_and_delete(): void
    {
        $http = new Client([
            new Response(200, body: new Stream('{"id":"web","status":"idle","url":"http://web.localhost","desiredReplicas":0,"availableReplicas":0}')),
            new Response(200, body: new Stream('{"deployments":[{"id":"web","status":"ready","url":"http://web.localhost"}]}')),
            new Response(204),
        ]);
        $deployments = new Deployments($http);

        $status = $deployments->get('web');
        $this->assertSame(DeploymentState::Idle, $status->status);
        $this->assertNotInstanceOf(TrafficMode::class, $status->mode);

        $list = $deployments->list();
        $this->assertCount(1, $list->deployments);
        $this->assertSame(DeploymentState::Ready, $list->deployments[0]->status);

        $deployments->delete('web');

        $this->assertSame('/v1/deployments/web', (string) $http->requests[0]->getUri());
        $this->assertSame('/v1/deployments', (string) $http->requests[1]->getUri());
        $this->assertSame('DELETE', $http->requests[2]->getMethod());
    }

    public function test_revisions_returns_history_and_traffic(): void
    {
        $http = new Client([new Response(200, body: new Stream(
            '{"revisions":["web-00002","web-00001"],"traffic":[{"revisionName":"web-00002","percent":100}]}'
        ))]);

        $revisions = new Deployments($http)->revisions('web');

        $this->assertSame(['web-00002', 'web-00001'], $revisions->revisions);
        $this->assertSame('web-00002', $revisions->traffic[0]->revisionName);
        $this->assertSame('/v1/deployments/web/revisions', (string) $http->requests[0]->getUri());
    }

    public function test_set_traffic_pins_a_canary_split(): void
    {
        $http = new Client([new Response(200, body: new Stream(
            '{"id":"web","status":"ready","url":"http://web.localhost","mode":"manual"}'
        ))]);

        $status = new Deployments($http)->setTraffic('web', [
            new TrafficTarget('web-00001', 90),
            new TrafficTarget('web-00002', 10),
        ]);

        $this->assertSame(TrafficMode::Manual, $status->mode);
        $this->assertSame('/v1/deployments/web/traffic', (string) $http->requests[0]->getUri());
        $this->assertJsonStringEqualsJsonString(
            '{"targets":[{"revisionName":"web-00001","percent":90},{"revisionName":"web-00002","percent":10}]}',
            (string) $http->requests[0]->getBody(),
        );
    }

    public function test_release_posts_an_empty_target_list(): void
    {
        $http = new Client([new Response(200, body: new Stream('{"id":"web","status":"ready","url":"http://web.localhost","mode":"auto"}'))]);

        $this->assertSame(TrafficMode::Auto, new Deployments($http)->release('web')->mode);
        $this->assertJsonStringEqualsJsonString('{"targets":[]}', (string) $http->requests[0]->getBody());
    }

    public function test_host_conflict_raises_an_api_exception(): void
    {
        $http = new Client([new Response(409, body: new Stream('{"error":"host acme.com is owned by deployment other"}'))]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('host acme.com is owned by deployment other');

        new Deployments($http)->apply(id: 'web', image: 'nginx', port: 80, hosts: ['acme.com']);
    }
}
