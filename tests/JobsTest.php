<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Tests;

use OpenRuntimes\Orchestrator\Enum\CallbackEvent;
use OpenRuntimes\Orchestrator\Enum\JobState;
use OpenRuntimes\Orchestrator\Exception\ApiException;
use OpenRuntimes\Orchestrator\Exception\ClientException;
use OpenRuntimes\Orchestrator\Exception\TimeoutException;
use OpenRuntimes\Orchestrator\Jobs;
use OpenRuntimes\Orchestrator\Model\Artifact\ArchiveArtifact;
use OpenRuntimes\Orchestrator\Model\Artifact\DownloadArtifact;
use OpenRuntimes\Orchestrator\Model\Artifact\ListArtifact;
use OpenRuntimes\Orchestrator\Model\Artifact\StatArtifact;
use OpenRuntimes\Orchestrator\Model\Artifact\UploadArtifact;
use OpenRuntimes\Orchestrator\Model\Callback;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Utopia\Client\Exception\ConnectionException;
use Utopia\Client\Exception\TimeoutException as TransportTimeoutException;
use Utopia\Psr7\Request\Factory as RequestFactory;
use Utopia\Psr7\Response;
use Utopia\Psr7\Stream;

final class JobsTest extends TestCase
{
    public function test_create_sends_json_and_hydrates_response(): void
    {
        $http = new Client([new Response(202, body: new Stream('{"id":"job-1","status":"accepted"}'))]);
        $jobs = new Jobs($http);

        $response = $jobs->create(id: 'job-1', image: 'alpine', command: 'echo ok');

        $this->assertSame('job-1', $response->id);
        $this->assertSame(JobState::Accepted, $response->status);

        $request = $http->requests[0];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1/jobs', (string) $request->getUri());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertJsonStringEqualsJsonString('{"id":"job-1","image":"alpine","command":"echo ok","cpu":1,"memory":512,"timeoutSeconds":1800,"workspace":"/workspace"}', (string) $request->getBody());
    }

    public function test_create_serializes_artifacts_and_callback(): void
    {
        $http = new Client([new Response(202, body: new Stream('{"id":"project-deployment-build","status":"accepted"}'))]);
        $jobs = new Jobs($http);

        $jobs->create(
            id: 'project-deployment-build',
            image: 'runtime:latest',
            command: 'build.sh',
            cpu: 0.5,
            memory: 1024,
            timeoutSeconds: 600,
            workspace: '/tmp',
            meta: ['projectId' => 'project'],
            environment: ['A' => 'B'],
            artifacts: [
                new DownloadArtifact('source', 'https://example.com/source', 'code.tar.gz', headers: ['X-Appwrite-Project' => 'project']),
                new ArchiveArtifact('build', 'build-output', 'build.tar.gz', depends: 'job'),
                new UploadArtifact('upload', 'build.tar.gz', 'https://example.com/upload', depends: 'build', headers: ['X-Appwrite-Project' => 'project']),
                new ListArtifact('files', 'output', recursive: false, excludes: ['node_modules'], depends: 'job'),
                new StatArtifact('size', 'build.tar.gz', depends: 'build'),
            ],
            callback: new Callback(
                url: 'https://example.com/events',
                events: [CallbackEvent::Log, CallbackEvent::Artifact, CallbackEvent::Exit],
                key: 'secret',
                headers: ['X-Appwrite-Project' => 'project'],
            ),
        );

        $this->assertJsonStringEqualsJsonString(
            (string) \json_encode([
                'id' => 'project-deployment-build',
                'image' => 'runtime:latest',
                'command' => 'build.sh',
                'cpu' => 0.5,
                'memory' => 1024,
                'timeoutSeconds' => 600,
                'workspace' => '/tmp',
                'meta' => ['projectId' => 'project'],
                'environment' => ['A' => 'B'],
                'artifacts' => [
                    ['id' => 'source', 'type' => 'download', 'in' => 'https://example.com/source', 'out' => 'code.tar.gz', 'headers' => ['X-Appwrite-Project' => 'project']],
                    ['id' => 'build', 'type' => 'archive', 'depends' => 'job', 'in' => 'build-output', 'out' => 'build.tar.gz', 'format' => 'tar.gz'],
                    ['id' => 'upload', 'type' => 'upload', 'depends' => 'build', 'in' => 'build.tar.gz', 'out' => 'https://example.com/upload', 'headers' => ['X-Appwrite-Project' => 'project']],
                    ['id' => 'files', 'type' => 'list', 'depends' => 'job', 'in' => 'output', 'recursive' => false, 'excludes' => ['node_modules']],
                    ['id' => 'size', 'type' => 'stat', 'depends' => 'build', 'in' => 'build.tar.gz'],
                ],
                'callback' => [
                    'url' => 'https://example.com/events',
                    'events' => ['orchestrator.job.log', 'orchestrator.job.artifact', 'orchestrator.job.exit'],
                    'key' => 'secret',
                    'headers' => ['X-Appwrite-Project' => 'project'],
                ],
            ]),
            (string) $http->requests[0]->getBody(),
        );
    }

    public function test_get_list_and_delete_use_expected_paths(): void
    {
        $http = new Client([
            new Response(200, body: new Stream('{"id":"job/1","status":"completed","exitCode":0}')),
            new Response(200, body: new Stream('{"jobs":[{"id":"job-2","status":"running"}]}')),
            new Response(204),
        ]);
        $jobs = new Jobs($http);

        $status = $jobs->get('job/1');
        $list = $jobs->list();
        $jobs->delete('job/1');

        $this->assertSame(JobState::Completed, $status->status);
        $this->assertSame(0, $status->exitCode);
        $this->assertSame('job-2', $list->jobs[0]->id);
        $this->assertSame('/v1/jobs/job%2F1', (string) $http->requests[0]->getUri());
        $this->assertSame('/v1/jobs', (string) $http->requests[1]->getUri());
        $this->assertSame('DELETE', $http->requests[2]->getMethod());
    }

    public function test_api_errors_expose_status_and_decoded_error(): void
    {
        $http = new Client([new Response(409, body: new Stream('{"error":"job already exists"}'))]);
        $jobs = new Jobs($http);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('job already exists');

        try {
            $jobs->create(id: 'job-1', image: 'alpine', command: 'echo ok');
        } catch (ApiException $e) {
            $this->assertSame(409, $e->statusCode);
            $this->assertSame(['error' => 'job already exists'], $e->decodedBody);
            throw $e;
        }
    }

    public function test_redirect_responses_are_not_successful(): void
    {
        $http = new Client([new Response(302)]);
        $jobs = new Jobs($http);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Orchestrator request failed.');

        try {
            $jobs->delete('job-1');
        } catch (ApiException $e) {
            $this->assertSame(302, $e->statusCode);
            throw $e;
        }
    }

    public function test_empty_create_response_becomes_client_exception(): void
    {
        $http = new Client([new Response(202)]);
        $jobs = new Jobs($http);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid job response: missing string id.');

        $jobs->create(id: 'job-1', image: 'alpine', command: 'echo ok');
    }

    public function test_transport_exceptions_become_client_exceptions(): void
    {
        $http = new Client(exception: new ConnectionException($this->request(), 'connection refused'));
        $jobs = new Jobs($http);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('connection refused');

        $jobs->list();
    }

    public function test_transport_timeouts_become_timeout_exceptions(): void
    {
        $exception = new TransportTimeoutException($this->request(), 'Operation timed out after 1000 milliseconds');
        $http = new Client(exception: $exception);
        $jobs = new Jobs($http);

        $this->expectException(TimeoutException::class);
        $this->expectExceptionMessage('Orchestrator request timed out.');

        try {
            $jobs->create(id: 'job-1', image: 'alpine', command: 'echo ok');
        } catch (TimeoutException $e) {
            $this->assertSame($exception, $e->getPrevious());
            throw $e;
        }
    }

    private function request(): RequestInterface
    {
        return new RequestFactory()->createRequest('GET', 'https://orchestrator.test/v1/jobs');
    }
}
