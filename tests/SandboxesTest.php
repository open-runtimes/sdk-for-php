<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Tests;

use OpenRuntimes\Orchestrator\Enum\RuntimeClass;
use OpenRuntimes\Orchestrator\Enum\SandboxState;
use OpenRuntimes\Orchestrator\Exception\ApiException;
use OpenRuntimes\Orchestrator\Exception\ClientException;
use OpenRuntimes\Orchestrator\Model\Artifact\DownloadArtifact;
use OpenRuntimes\Orchestrator\Model\Artifact\UnarchiveArtifact;
use OpenRuntimes\Orchestrator\Sandboxes;
use PHPUnit\Framework\TestCase;
use Utopia\Psr7\Response;
use Utopia\Psr7\Stream;

final class SandboxesTest extends TestCase
{
    public function test_create_from_a_pool_hydrates_urls(): void
    {
        $http = new Client([new Response(201, body: new Stream(
            '{"id":"py-3f9c1a02","poolId":"py","status":"ready","url":"http://s-abc.sandboxes.test",'
            .'"urls":{"3000":"http://s-abc.sandboxes.test","5173":"http://s-abc-5173.sandboxes.test"}}'
        ))]);

        $sandbox = new Sandboxes($http)->create(
            pool: 'py',
            ports: [5173],
            artifacts: [
                new DownloadArtifact('code', 'https://acme.test/app.tar.gz', 'app.tar.gz'),
                new UnarchiveArtifact('unpack', 'app.tar.gz', '.', depends: 'code'),
            ],
            timeoutSeconds: 0,
            idleTimeoutSeconds: 900,
        );

        $this->assertSame('py-3f9c1a02', $sandbox->id);
        $this->assertSame('py', $sandbox->poolId);
        $this->assertSame(SandboxState::Ready, $sandbox->status);
        $this->assertSame('http://s-abc.sandboxes.test', $sandbox->url);
        $this->assertSame([
            '3000' => 'http://s-abc.sandboxes.test',
            '5173' => 'http://s-abc-5173.sandboxes.test',
        ], $sandbox->urls);
        $this->assertNull($sandbox->error);

        $request = $http->requests[0];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1/sandbox', (string) $request->getUri());
        $this->assertJsonStringEqualsJsonString(
            '{"pool":"py","ports":[5173],"artifacts":['
            .'{"id":"code","type":"download","in":"https://acme.test/app.tar.gz","out":"app.tar.gz"},'
            .'{"id":"unpack","type":"unarchive","depends":"code","in":"app.tar.gz","out":"."}'
            .'],"timeoutSeconds":0,"idleTimeoutSeconds":900}',
            (string) $request->getBody(),
        );
    }

    public function test_create_without_a_pool_sizes_the_pod(): void
    {
        $http = new Client([new Response(201, body: new Stream('{"id":"sbx-1","status":"ready","url":"http://s-abc.sandboxes.test"}'))]);

        $sandbox = new Sandboxes($http)->create(
            image: 'python:3.12-slim',
            port: 3000,
            cpu: 2.0,
            memory: 2048,
            runtimeClass: RuntimeClass::Gvisor,
        );

        $this->assertNull($sandbox->poolId);
        $this->assertJsonStringEqualsJsonString(
            '{"image":"python:3.12-slim","port":3000,"cpu":2,"memory":2048,"runtimeClass":"gvisor"}',
            (string) $http->requests[0]->getBody(),
        );
    }

    public function test_create_requires_exactly_one_of_pool_or_image(): void
    {
        $http = new Client;

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('exactly one of pool or image');

        new Sandboxes($http)->create(pool: 'py', image: 'python:3.12-slim', port: 3000);
    }

    public function test_create_from_an_image_requires_a_port(): void
    {
        $http = new Client;

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('requires a port');

        new Sandboxes($http)->create(image: 'python:3.12-slim');
    }

    public function test_a_failed_sandbox_is_a_status_not_an_error(): void
    {
        $http = new Client([new Response(201, body: new Stream(
            '{"id":"py-1","poolId":"py","status":"failed","error":"artifact code: 404"}'
        ))]);

        $sandbox = new Sandboxes($http)->create(pool: 'py');

        $this->assertSame(SandboxState::Failed, $sandbox->status);
        $this->assertSame('artifact code: 404', $sandbox->error);
        $this->assertNull($sandbox->url);
    }

    public function test_get_list_and_delete(): void
    {
        $http = new Client([
            new Response(200, body: new Stream('{"id":"py-1","poolId":"py","status":"creating"}')),
            new Response(200, body: new Stream('{"sandboxes":[{"id":"py-1","poolId":"py","status":"ready","url":"http://s-abc.sandboxes.test"}]}')),
            new Response(204),
        ]);
        $sandboxes = new Sandboxes($http);

        $this->assertSame(SandboxState::Creating, $sandboxes->get('py-1')->status);
        $this->assertCount(1, $sandboxes->list()->sandboxes);
        $sandboxes->delete('py-1');

        $this->assertSame('/v1/sandbox/py-1', (string) $http->requests[0]->getUri());
        $this->assertSame('/v1/sandbox', (string) $http->requests[1]->getUri());
        $this->assertSame('DELETE', $http->requests[2]->getMethod());
    }

    public function test_pools_are_read_only(): void
    {
        $http = new Client([
            new Response(200, body: new Stream('{"pools":[{"id":"py","image":"python:3.12-slim","size":4,"warm":4,"claimed":1}]}')),
            new Response(200, body: new Stream('{"id":"py","image":"python:3.12-slim","size":4,"warm":3,"claimed":1}')),
        ]);
        $sandboxes = new Sandboxes($http);

        $pools = $sandboxes->pools();
        $this->assertCount(1, $pools->pools);
        $this->assertSame(4, $pools->pools[0]->warm);

        $pool = $sandboxes->pool('py');
        $this->assertSame('python:3.12-slim', $pool->image);
        $this->assertSame(3, $pool->warm);
        $this->assertSame('/v1/sandbox-pool', (string) $http->requests[0]->getUri());
        $this->assertSame('/v1/sandbox-pool/py', (string) $http->requests[1]->getUri());
    }

    public function test_an_exhausted_rejecting_pool_raises_an_api_exception(): void
    {
        $http = new Client([new Response(429, body: new Stream('{"error":"pool py has no warm pod"}'))]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('pool py has no warm pod');

        new Sandboxes($http)->create(pool: 'py');
    }
}
