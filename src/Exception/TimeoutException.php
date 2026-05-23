<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Exception;

use Throwable;

final class TimeoutException extends ClientException
{
    public function __construct(string $message, public readonly int $timeoutSeconds, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
