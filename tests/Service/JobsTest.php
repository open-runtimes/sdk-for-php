<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Tests\Service;

use OpenRuntimes\Orchestrator\Client as OrchestratorClient;
use OpenRuntimes\Orchestrator\DTO\JobRequest;
use OpenRuntimes\Orchestrator\Enum\JobState;
use OpenRuntimes\Orchestrator\Exception\ApiException;
use OpenRuntimes\Orchestrator\Exception\ClientException;
use OpenRuntimes\Orchestrator\Exception\TimeoutException;
use OpenRuntimes\Orchestrator\Tests\Client as TestClient;
use PHPUnit\Framework\TestCase;
use Utopia\Fetch\Exception as FetchException;
use Utopia\Fetch\Response;

final class JobsTest extends TestCase
{
    public function test_create_sends_json_and_hydrates_response(): void
    {
        $adapter = new TestClient([new Response(202, '{"id":"job-1","status":"accepted"}', [])]);
        $jobs = new OrchestratorClient('https://orchestrator.test', apiKey: 'key', adapter: $adapter)->jobs();

        $response = $jobs->create(new JobRequest('job-1', 'alpine', 'echo ok'), 60);

        $this->assertSame('job-1', $response->id);
        $this->assertSame(JobState::Accepted, $response->status);
        $this->assertSame('POST', $adapter->requests[0]['method']);
        $this->assertSame('https://orchestrator.test/v1/jobs', $adapter->requests[0]['url']);
        $this->assertSame('Bearer key', $adapter->requests[0]['headers']['authorization']);
        $this->assertSame('application/json', $adapter->requests[0]['headers']['content-type']);
        $this->assertSame(60000, $adapter->requests[0]['options']->getTimeout());
        $this->assertSame(5000, $adapter->requests[0]['options']->getConnectTimeout());
        $this->assertFalse($adapter->requests[0]['options']->getAllowRedirects());
        $this->assertSame(OrchestratorClient::USER_AGENT, $adapter->requests[0]['options']->getUserAgent());
        $this->assertJsonStringEqualsJsonString('{"id":"job-1","image":"alpine","command":"echo ok","cpu":1,"memory":512,"timeoutSeconds":1800,"workspace":"/workspace"}', (string) $adapter->requests[0]['body']);
    }

    public function test_custom_user_agent_is_sent(): void
    {
        $adapter = new TestClient([new Response(200, '{"jobs":[]}', [])]);
        $jobs = new OrchestratorClient(
            'https://orchestrator.test',
            adapter: $adapter,
            userAgent: 'custom-client/1.0',
        )->jobs();

        $jobs->list();

        $this->assertSame('custom-client/1.0', $adapter->requests[0]['options']->getUserAgent());
    }

    public function test_get_list_and_delete_use_expected_paths(): void
    {
        $adapter = new TestClient([
            new Response(200, '{"id":"job/1","status":"completed","exitCode":0}', []),
            new Response(200, '{"jobs":[{"id":"job-2","status":"running"}]}', []),
            new Response(204, '', []),
        ]);
        $jobs = new OrchestratorClient('https://orchestrator.test/', adapter: $adapter)->jobs();

        $status = $jobs->get('job/1');
        $list = $jobs->list();
        $jobs->delete('job/1');

        $this->assertSame(JobState::Completed, $status->status);
        $this->assertSame(0, $status->exitCode);
        $this->assertSame('job-2', $list->jobs[0]->id);
        $this->assertSame('https://orchestrator.test/v1/jobs/job%2F1', $adapter->requests[0]['url']);
        $this->assertSame('https://orchestrator.test/v1/jobs', $adapter->requests[1]['url']);
        $this->assertSame('DELETE', $adapter->requests[2]['method']);
    }

    public function test_api_errors_expose_status_and_decoded_error(): void
    {
        $adapter = new TestClient([new Response(409, '{"error":"job already exists"}', [])]);
        $jobs = new OrchestratorClient('https://orchestrator.test', adapter: $adapter)->jobs();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('job already exists');

        try {
            $jobs->create(new JobRequest('job-1', 'alpine', 'echo ok'));
        } catch (ApiException $e) {
            $this->assertSame(409, $e->statusCode);
            $this->assertSame(['error' => 'job already exists'], $e->decodedBody);
            throw $e;
        }
    }

    public function test_empty_create_response_becomes_client_exception(): void
    {
        $adapter = new TestClient([new Response(202, '', [])]);
        $jobs = new OrchestratorClient('https://orchestrator.test', adapter: $adapter)->jobs();

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid job response: missing string id.');

        $jobs->create(new JobRequest('job-1', 'alpine', 'echo ok'));
    }

    public function test_fetch_exceptions_become_client_exceptions(): void
    {
        $adapter = new TestClient(exception: new FetchException('connection refused'));
        $jobs = new OrchestratorClient('https://orchestrator.test', adapter: $adapter)->jobs();

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('connection refused');

        $jobs->list();
    }

    public function test_timeout_fetch_exceptions_become_timeout_exceptions(): void
    {
        $adapter = new TestClient(
            exception: new FetchException('request failed'),
            delayMicroseconds: 1_100_000,
        );
        $jobs = new OrchestratorClient('https://orchestrator.test', adapter: $adapter)->jobs();

        $this->expectException(TimeoutException::class);
        $this->expectExceptionMessage('Orchestrator request timed out.');

        try {
            $jobs->create(new JobRequest('job-1', 'alpine', 'echo ok'), 1);
        } catch (TimeoutException $e) {
            $this->assertSame(1, $e->timeoutSeconds);
            throw $e;
        }
    }
}
