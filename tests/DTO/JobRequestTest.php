<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Tests\DTO;

use OpenRuntimes\Orchestrator\DTO\Artifact\ArchiveArtifact;
use OpenRuntimes\Orchestrator\DTO\Artifact\DownloadArtifact;
use OpenRuntimes\Orchestrator\DTO\Artifact\ListArtifact;
use OpenRuntimes\Orchestrator\DTO\Artifact\UploadArtifact;
use OpenRuntimes\Orchestrator\DTO\Callback;
use OpenRuntimes\Orchestrator\DTO\JobRequest;
use OpenRuntimes\Orchestrator\Enum\CallbackEvent;
use PHPUnit\Framework\TestCase;

final class JobRequestTest extends TestCase
{
    public function test_omits_empty_maps(): void
    {
        $request = new JobRequest('job-1', 'alpine', 'echo ok');

        $this->assertSame([
            'id' => 'job-1',
            'image' => 'alpine',
            'command' => 'echo ok',
            'cpu' => 1.0,
            'memory' => 512,
            'timeoutSeconds' => 1800,
            'workspace' => '/workspace',
        ], $request->toArray());
    }

    public function test_serializes_job_request_with_artifacts_and_callback(): void
    {
        $request = new JobRequest(
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
                new UploadArtifact('upload', 'build.tar.gz', 'https://example.com/upload', depends: 'build', headers: ['X-Appwrite-Project' => 'project'], chunked: true),
                new ListArtifact('files', 'output', recursive: false, excludes: ['node_modules'], depends: 'job'),
            ],
            callback: new Callback(
                url: 'https://example.com/events',
                events: [CallbackEvent::Log, CallbackEvent::Artifact, CallbackEvent::Exit],
                key: 'secret',
                headers: ['X-Appwrite-Project' => 'project'],
            ),
        );

        $this->assertSame([
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
                [
                    'id' => 'source',
                    'type' => 'download',
                    'in' => 'https://example.com/source',
                    'out' => 'code.tar.gz',
                    'headers' => ['X-Appwrite-Project' => 'project'],
                ],
                [
                    'id' => 'build',
                    'type' => 'archive',
                    'depends' => 'job',
                    'in' => 'build-output',
                    'out' => 'build.tar.gz',
                    'format' => 'tar.gz',
                ],
                [
                    'id' => 'upload',
                    'type' => 'upload',
                    'depends' => 'build',
                    'in' => 'build.tar.gz',
                    'out' => 'https://example.com/upload',
                    'headers' => ['X-Appwrite-Project' => 'project'],
                    'chunked' => true,
                ],
                [
                    'id' => 'files',
                    'type' => 'list',
                    'depends' => 'job',
                    'in' => 'output',
                    'recursive' => false,
                    'excludes' => ['node_modules'],
                ],
            ],
            'callback' => [
                'url' => 'https://example.com/events',
                'events' => ['orchestrator.job.log', 'orchestrator.job.artifact', 'orchestrator.job.exit'],
                'key' => 'secret',
                'headers' => ['X-Appwrite-Project' => 'project'],
            ],
        ], $request->toArray());
    }
}
