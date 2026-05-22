<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Exception;

final class TimeoutException extends ClientException
{
    public function __construct(string $message, public readonly int $timeoutSeconds)
    {
        parent::__construct($message);
    }
}
