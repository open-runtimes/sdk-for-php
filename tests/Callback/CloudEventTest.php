<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Tests\Callback;

use OpenRuntimes\Orchestrator\Callback\CloudEvent;
use OpenRuntimes\Orchestrator\Exception\ClientException;
use PHPUnit\Framework\TestCase;

final class CloudEventTest extends TestCase
{
    public function test_requires_time(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid CloudEvent: missing string time.');

        CloudEvent::fromArray([]);
    }

    public function test_rejects_malformed_time(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid CloudEvent: malformed time.');

        CloudEvent::fromArray(['time' => 'not-a-time']);
    }
}
