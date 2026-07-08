<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Tests;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Utopia\Psr7\Response;
use Utopia\Psr7\Stream;

/**
 * Network-free PSR-18 client test double: it records the requests it receives and
 * returns preset responses (or throws) instead of dialling out.
 */
final class Client implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /**
     * @param  list<ResponseInterface>  $responses
     */
    public function __construct(
        private array $responses = [],
        private readonly ?ClientExceptionInterface $exception = null,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->exception instanceof ClientExceptionInterface) {
            throw $this->exception;
        }

        return \array_shift($this->responses) ?? new Response(500, body: new Stream('{"error":"missing fake response"}'));
    }
}
