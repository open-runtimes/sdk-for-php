<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator;

use JsonException;
use OpenRuntimes\Orchestrator\Exception\ApiException;
use OpenRuntimes\Orchestrator\Exception\ClientException;
use OpenRuntimes\Orchestrator\Exception\TimeoutException;
use OpenRuntimes\Orchestrator\Models\Artifact\Artifact;
use OpenRuntimes\Orchestrator\Models\Callback;
use OpenRuntimes\Orchestrator\Models\JobCreated;
use OpenRuntimes\Orchestrator\Models\JobList;
use OpenRuntimes\Orchestrator\Models\JobStatus;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Utopia\Client\Adapter\Curl\Client as CurlAdapter;
use Utopia\Client as HttpClient;
use Utopia\Client\Exception\TimeoutException as TransportTimeoutException;
use Utopia\Psr7\Method;
use Utopia\Psr7\Request\Factory as RequestFactory;

final readonly class Jobs
{
    private RequestFactory $factory;

    public function __construct(
        private ClientInterface $client = new HttpClient(new CurlAdapter),
    ) {
        $this->factory = new RequestFactory;
    }

    /**
     * @param  array<string, string>  $meta
     * @param  array<string, string>  $environment
     * @param  list<Artifact>  $artifacts
     */
    public function create(
        string $id,
        string $image,
        string $command,
        float $cpu = 1.0,
        int $memory = 512,
        int $timeoutSeconds = 1800,
        string $workspace = '/workspace',
        array $meta = [],
        array $environment = [],
        array $artifacts = [],
        ?Callback $callback = null,
    ): JobCreated {
        $payload = [
            'id' => $id,
            'image' => $image,
            'command' => $command,
            'cpu' => $cpu,
            'memory' => $memory,
            'timeoutSeconds' => $timeoutSeconds,
            'workspace' => $workspace,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        if ($environment !== []) {
            $payload['environment'] = $environment;
        }

        if ($artifacts !== []) {
            $payload['artifacts'] = \array_map(static fn (Artifact $artifact): array => $artifact->toArray(), $artifacts);
        }

        if ($callback instanceof Callback) {
            $payload['callback'] = $callback->toArray();
        }

        return JobCreated::fromArray($this->json(Method::POST, '/v1/jobs', $payload));
    }

    public function get(string $jobId): JobStatus
    {
        return JobStatus::fromArray($this->json(Method::GET, '/v1/jobs/'.\rawurlencode($jobId)));
    }

    public function list(): JobList
    {
        return JobList::fromArray($this->json(Method::GET, '/v1/jobs'));
    }

    public function delete(string $jobId): void
    {
        $this->assertSuccess($this->send(Method::DELETE, '/v1/jobs/'.\rawurlencode($jobId)));
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function json(string $method, string $path, ?array $payload = null): array
    {
        $response = $this->send($method, $path, $payload);
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
     * @param  array<string, mixed>|null  $payload
     */
    private function send(string $method, string $path, ?array $payload = null): ResponseInterface
    {
        try {
            $request = $payload === null
                ? $this->factory->createRequest($method, $path)
                : $this->factory->json($method, $path, $payload);
        } catch (JsonException $e) {
            throw new ClientException("Failed to encode orchestrator request: {$e->getMessage()}");
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
