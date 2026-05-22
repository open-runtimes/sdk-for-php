<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Tests\Callback;

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
}
