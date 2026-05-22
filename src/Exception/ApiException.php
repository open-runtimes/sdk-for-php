<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Exception;

final class ApiException extends OrchestratorException
{
    /**
     * @param  array<string, mixed>|null  $decodedBody
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $body,
        public readonly ?array $decodedBody = null,
    ) {
        parent::__construct($message, $statusCode);
    }
}
