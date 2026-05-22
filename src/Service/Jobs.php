<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Service;

use JsonException;
use OpenRuntimes\Orchestrator\DTO\JobList;
use OpenRuntimes\Orchestrator\DTO\JobRequest;
use OpenRuntimes\Orchestrator\DTO\JobResponse;
use OpenRuntimes\Orchestrator\DTO\JobStatus;
use OpenRuntimes\Orchestrator\Exception\ApiException;
use OpenRuntimes\Orchestrator\Exception\ClientException;
use OpenRuntimes\Orchestrator\Exception\TimeoutException;
use Utopia\Fetch\Adapter;
use Utopia\Fetch\Client;
use Utopia\Fetch\Exception as FetchException;
use Utopia\Fetch\Response;

final readonly class Jobs
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        private string $endpoint,
        private ?string $apiKey,
        private int $timeoutSeconds,
        private array $headers,
        private ?Adapter $adapter = null,
    ) {}

    public function create(JobRequest $request, ?int $timeoutSeconds = null): JobResponse
    {
        $data = $this->json('POST', '/v1/jobs', $request->toArray(), $timeoutSeconds);

        return JobResponse::fromArray($data);
    }

    public function get(string $jobId): JobStatus
    {
        $data = $this->json('GET', '/v1/jobs/'.\rawurlencode($jobId));

        return JobStatus::fromArray($data);
    }

    public function list(): JobList
    {
        $data = $this->json('GET', '/v1/jobs');

        return JobList::fromArray($data);
    }

    public function delete(string $jobId): void
    {
        $response = $this->send('DELETE', '/v1/jobs/'.\rawurlencode($jobId));
        $this->assertSuccess($response);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function json(string $method, string $path, ?array $payload = null, ?int $timeoutSeconds = null): array
    {
        $response = $this->send($method, $path, $payload, $timeoutSeconds);
        $this->assertSuccess($response);

        if ($response->text() === '') {
            return [];
        }

        try {
            $decoded = \json_decode($response->text(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ClientException('Failed to decode orchestrator response: '.$e->getMessage(), $response->getStatusCode());
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
    private function send(string $method, string $path, ?array $payload = null, ?int $timeoutSeconds = null): Response
    {
        $timeoutSeconds ??= $this->timeoutSeconds;
        $timeoutMs = $timeoutSeconds * 1000;
        $connectTimeoutMs = min(5000, $timeoutMs);

        $client = new Client($this->adapter);
        $headers = [
            ...$this->headers,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer '.$this->apiKey;
        }

        foreach ($headers as $key => $value) {
            $client->addHeader($key, $value);
        }

        $body = null;
        if ($payload !== null) {
            try {
                $body = \json_encode($payload, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new ClientException('Failed to encode orchestrator request: '.$e->getMessage());
            }
        }

        $startedAt = \microtime(true);

        try {
            return $client
                ->setAllowRedirects(false)
                ->fetch(
                    url: $this->endpoint.$path,
                    method: $method,
                    body: $body,
                    timeoutMs: $timeoutMs,
                    connectTimeoutMs: $connectTimeoutMs,
                );
        } catch (FetchException $e) {
            if ($timeoutSeconds > 0 && \microtime(true) - $startedAt >= $timeoutSeconds) {
                throw new TimeoutException('Orchestrator request timed out.', $timeoutSeconds);
            }

            throw new ClientException($e->getMessage(), previous: $e);
        }
    }

    private function assertSuccess(Response $response): void
    {
        if ($response->getStatusCode() < 400) {
            return;
        }

        $decoded = null;
        $body = $response->text();
        $message = $body === '' ? 'Orchestrator request failed.' : $body;
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

        throw new ApiException($message, $response->getStatusCode(), $body, $decoded);
    }
}
