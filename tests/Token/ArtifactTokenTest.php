<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Tests\Token;

use OpenRuntimes\Orchestrator\Exception\ClientException;
use OpenRuntimes\Orchestrator\Token\ArtifactToken;
use PHPUnit\Framework\TestCase;

final class ArtifactTokenTest extends TestCase
{
    public function test_creates_and_verifies_artifact_token(): void
    {
        $token = ArtifactToken::create('secret', 'project', 'resource', 'deployment', 'source');

        $this->assertTrue(ArtifactToken::verify('secret', $token, 'project', 'resource', 'deployment', 'source'));
        $this->assertFalse(ArtifactToken::verify('wrong', $token, 'project', 'resource', 'deployment', 'source'));
    }

    public function test_exposes_payload(): void
    {
        $token = ArtifactToken::create('secret', 'project', 'resource', 'deployment', 'build');
        $payload = ArtifactToken::payload('secret', $token);

        $this->assertSame('project', $payload['projectId']);
        $this->assertSame('resource', $payload['resourceId']);
        $this->assertSame('deployment', $payload['deploymentId']);
        $this->assertSame('build', $payload['purpose']);
        $this->assertIsInt($payload['expires']);
    }

    public function test_rejects_invalid_token_payload(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid artifact token.');

        ArtifactToken::payload('secret', 'not-a-token');
    }

    public function test_rejects_expired_or_mismatched_token(): void
    {
        $expired = ArtifactToken::create('secret', 'project', 'resource', 'deployment', 'source', -1);
        $valid = ArtifactToken::create('secret', 'project', 'resource', 'deployment', 'source');

        $this->assertFalse(ArtifactToken::verify('secret', $expired, 'project', 'resource', 'deployment', 'source'));
        $this->assertFalse(ArtifactToken::verify('secret', $valid, 'project', 'resource', 'deployment', 'build'));
    }
}
