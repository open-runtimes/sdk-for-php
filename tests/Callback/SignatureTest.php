<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Tests\Callback;

use DateTimeImmutable;
use DateTimeInterface;
use OpenRuntimes\Orchestrator\Callback\Signature;
use PHPUnit\Framework\TestCase;

final class SignatureTest extends TestCase
{
    public function test_verifies_hmac_signature(): void
    {
        $body = '{"type":"orchestrator.job.exit"}';
        $signature = Signature::sign($body, 'secret');

        $this->assertTrue(Signature::verify($body, $signature, 'secret'));
        $this->assertFalse(Signature::verify($body, $signature, 'wrong'));
        $this->assertFalse(Signature::verify($body, '', 'secret'));
    }

    public function test_verifies_signed_event_with_time_tolerance(): void
    {
        $body = \json_encode([
            'specversion' => '1.0',
            'type' => 'orchestrator.job.exit',
            'source' => 'orchestrator/service',
            'subject' => 'job-1',
            'id' => 'event-1',
            'time' => (new DateTimeImmutable)->format(DateTimeInterface::RFC3339_EXTENDED),
            'datacontenttype' => 'application/json',
            'data' => ['jobId' => 'job-1'],
        ], JSON_THROW_ON_ERROR);

        $this->assertTrue(Signature::verifyEvent($body, Signature::sign($body, 'secret'), 'secret'));
        $this->assertFalse(Signature::verifyEvent($body, Signature::sign($body, 'wrong'), 'secret'));
    }

    public function test_rejects_replayed_signed_event(): void
    {
        $body = \json_encode([
            'specversion' => '1.0',
            'type' => 'orchestrator.job.exit',
            'source' => 'orchestrator/service',
            'subject' => 'job-1',
            'id' => 'event-1',
            'time' => new DateTimeImmutable('-10 minutes')->format(DateTimeInterface::RFC3339_EXTENDED),
            'datacontenttype' => 'application/json',
            'data' => ['jobId' => 'job-1'],
        ], JSON_THROW_ON_ERROR);

        $this->assertFalse(Signature::verifyEvent($body, Signature::sign($body, 'secret'), 'secret'));
    }
}
