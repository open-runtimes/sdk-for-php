<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator;

use JsonException;
use OpenRuntimes\Orchestrator\Exception\ApiException;
use OpenRuntimes\Orchestrator\Exception\ClientException;
use OpenRuntimes\Orchestrator\Exception\TimeoutException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Utopia\Client\Exception\TimeoutException as TransportTimeoutException;
use Utopia\Psr7\Request\Factory as RequestFactory;

/**
 * Shared HTTP plumbing for the orchestrator's service clients: request building,
 * error mapping, and JSON decoding.
 *
 * @internal
 */
final readonly class Transport
{
    private RequestFactory $factory;

    public function __construct(
        private ClientInterface $client,
    ) {
        $this->factory = new RequestFactory;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function json(string $method, string $path, ?array $payload = null, array $headers = []): array
    {
        $response = $this->send($method, $path, $payload, $headers);
        $this->assertSuccess($response);

        $body = (string) $response->getBody();
        if ($body === '') {
            return [];
        }

        try {
            $decoded = \json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ClientException("Failed to decode orchestrator response: {$e->getMessage()}", $response->getStatusCode());
        }

        if (! \is_array($decoded)) {
            throw new ClientException('Orchestrator response was not a JSON object.', $response->getStatusCode());
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Send a request whose response body carries nothing worth decoding.
     */
    public function discard(string $method, string $path): void
    {
        $this->assertSuccess($this->send($method, $path));
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, string>  $headers
     */
    private function send(string $method, string $path, ?array $payload = null, array $headers = []): ResponseInterface
    {
        try {
            $request = $payload === null
                ? $this->factory->createRequest($method, $path)
                : $this->factory->json($method, $path, $payload);
        } catch (JsonException $e) {
            throw new ClientException("Failed to encode orchestrator request: {$e->getMessage()}");
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        try {
            return $this->client->sendRequest($request);
        } catch (TransportTimeoutException $e) {
            throw new TimeoutException(previous: $e);
        } catch (ClientExceptionInterface $e) {
            throw new ClientException($e->getMessage(), previous: $e);
        }
    }

    private function assertSuccess(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            return;
        }

        $body = (string) $response->getBody();
        $message = $body === '' ? 'Orchestrator request failed.' : $body;
        $decoded = null;

        try {
            $candidate = \json_decode($body, true, flags: JSON_THROW_ON_ERROR);
            if (\is_array($candidate)) {
                /** @var array<string, mixed> $candidate */
                $decoded = $candidate;
                if (isset($candidate['error']) && \is_string($candidate['error'])) {
                    $message = $candidate['error'];
                }
            }
        } catch (JsonException) {
        }

        throw new ApiException($message, $status, $body, $decoded);
    }
}
