<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Tests;

use Utopia\Fetch\Adapter;
use Utopia\Fetch\Chunk;
use Utopia\Fetch\Exception;
use Utopia\Fetch\Options\Request as RequestOptions;
use Utopia\Fetch\Response;

final class Client implements Adapter
{
    /**
     * @var list<array{
     *     url: string,
     *     method: string,
     *     body: mixed,
     *     headers: array<string, string>,
     *     options: RequestOptions,
     *     chunkCallback: callable(Chunk): mixed|null
     * }>
     */
    public array $requests = [];

    /**
     * @param  list<Response>  $responses
     */
    public function __construct(
        private array $responses = [],
        private readonly ?Exception $exception = null,
        private readonly int $delayMicroseconds = 0,
    ) {}

    /**
     * @param  array<string, string>  $headers
     * @param  callable(Chunk): mixed|null  $chunkCallback
     */
    public function send(
        string $url,
        string $method,
        mixed $body,
        array $headers,
        RequestOptions $options,
        ?callable $chunkCallback = null
    ): Response {
        $this->requests[] = [
            'url' => $url,
            'method' => $method,
            'body' => $body,
            'headers' => $headers,
            'options' => $options,
            'chunkCallback' => $chunkCallback,
        ];

        if ($this->delayMicroseconds > 0) {
            \usleep($this->delayMicroseconds);
        }

        if ($this->exception instanceof Exception) {
            throw $this->exception;
        }

        return \array_shift($this->responses) ?? new Response(500, '{"error":"missing fake response"}', []);
    }
}
