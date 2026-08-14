<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Tests;

use OpenRuntimes\Orchestrator\DeploymentPools;
use OpenRuntimes\Orchestrator\Enum\ActivationState;
use OpenRuntimes\Orchestrator\Enum\CallbackEvent;
use OpenRuntimes\Orchestrator\Exception\ClientException;
use OpenRuntimes\Orchestrator\Model\Artifact\DownloadArtifact;
use OpenRuntimes\Orchestrator\Model\Callback;
use PHPUnit\Framework\TestCase;
use Utopia\Psr7\Response;
use Utopia\Psr7\Stream;

final class DeploymentPoolsTest extends TestCase
{
    public function test_list_and_get_pools(): void
    {
        $http = new Client([
            new Response(200, body: new Stream('{"pools":[{"id":"node","image":"node:22-slim","size":4,"warm":4,"claimed":0}]}')),
            new Response(200, body: new Stream('{"id":"node","image":"node:22-slim","size":4,"warm":2,"claimed":2}')),
        ]);
        $pools = new DeploymentPools($http);

        $this->assertSame('node', $pools->list()->pools[0]->id);
        $this->assertSame(2, $pools->get('node')->claimed);
        $this->assertSame('/v1/deployment-pools', (string) $http->requests[0]->getUri());
        $this->assertSame('/v1/deployment-pools/node', (string) $http->requests[1]->getUri());
    }

    public function test_activate_claims_a_warm_pod(): void
    {
        $http = new Client([new Response(201, body: new Stream(
            '{"id":"preview-7","poolId":"node","status":"ready","url":"http://preview-7.pools.test","podId":"pod-abc"}'
        ))]);

        $activation = new DeploymentPools($http)->activate(
            poolId: 'node',
            command: 'node server.js',
            id: 'preview-7',
            environment: ['NODE_ENV' => 'production'],
            artifacts: [new DownloadArtifact('code', 'https://acme.test/b.tar.gz', 'b.tar.gz')],
            idleTimeoutSeconds: 600,
        );

        $this->assertSame('preview-7', $activation->id);
        $this->assertSame('node', $activation->poolId);
        $this->assertSame(ActivationState::Ready, $activation->status);
        $this->assertSame('http://preview-7.pools.test', $activation->url);
        $this->assertSame('pod-abc', $activation->podId);

        $request = $http->requests[0];
        $this->assertSame('/v1/deployment-pools/node/activations', (string) $request->getUri());
        $this->assertFalse($request->hasHeader('Prefer'));
        $this->assertJsonStringEqualsJsonString(
            '{"command":"node server.js","id":"preview-7","environment":{"NODE_ENV":"production"},'
            .'"artifacts":[{"id":"code","type":"download","in":"https://acme.test/b.tar.gz","out":"b.tar.gz"}],'
            .'"idleTimeoutSeconds":600}',
            (string) $request->getBody(),
        );
    }

    public function test_async_activation_sends_prefer_and_accepts_a_status_without_an_id(): void
    {
        $http = new Client([new Response(202, body: new Stream('{"poolId":"node","status":"activating"}'))]);

        $activation = new DeploymentPools($http)->activate(
            poolId: 'node',
            command: 'node server.js',
            callback: new Callback(
                url: 'https://acme.test/hook',
                events: [CallbackEvent::PoolActivationResult],
                key: 'secret',
            ),
            async: true,
        );

        $this->assertNull($activation->id);
        $this->assertSame(ActivationState::Activating, $activation->status);
        $this->assertSame('respond-async', $http->requests[0]->getHeaderLine('Prefer'));
        $this->assertJsonStringEqualsJsonString(
            '{"command":"node server.js","callback":{"url":"https://acme.test/hook",'
            .'"events":["orchestrator.pool.activation.result"],"key":"secret"}}',
            (string) $http->requests[0]->getBody(),
        );
    }

    public function test_async_activation_requires_a_callback(): void
    {
        $http = new Client;

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('async activation requires a callback');

        new DeploymentPools($http)->activate(poolId: 'node', command: 'node server.js', async: true);
    }

    public function test_read_and_deactivate_activations(): void
    {
        $http = new Client([
            new Response(200, body: new Stream('{"activations":[{"id":"preview-7","poolId":"node","status":"ready","url":"http://preview-7.pools.test"}]}')),
            new Response(200, body: new Stream('{"id":"preview-7","poolId":"node","status":"failed","error":"workload exited"}')),
            new Response(204),
        ]);
        $pools = new DeploymentPools($http);

        $this->assertCount(1, $pools->activations('node')->activations);

        $activation = $pools->activation('node', 'preview-7');
        $this->assertSame(ActivationState::Failed, $activation->status);
        $this->assertSame('workload exited', $activation->error);
        $this->assertNull($activation->url);

        $pools->deactivate('node', 'preview-7');

        $this->assertSame('/v1/deployment-pools/node/activations', (string) $http->requests[0]->getUri());
        $this->assertSame('/v1/deployment-pools/node/activations/preview-7', (string) $http->requests[1]->getUri());
        $this->assertSame('DELETE', $http->requests[2]->getMethod());
    }
}
