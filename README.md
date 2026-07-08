# Orchestrator PHP Client

PHP SDK for the Open Runtimes orchestrator Jobs API.

Server: https://github.com/open-runtimes/orchestrator

```php
use OpenRuntimes\Orchestrator\Enum\CallbackEvent;
use OpenRuntimes\Orchestrator\Jobs;
use OpenRuntimes\Orchestrator\Model\Artifact\DownloadArtifact;
use OpenRuntimes\Orchestrator\Model\Artifact\UploadArtifact;
use OpenRuntimes\Orchestrator\Model\Callback;
use Utopia\Client;
use Utopia\Client\Adapter\Curl\Client as CurlAdapter;

$http = new Client(new CurlAdapter())
    ->withBaseUri('http://localhost:8080')
    ->withBearerAuth('secret');

$jobs = new Jobs($http);

$response = $jobs->create(
    id: 'build-001',
    image: 'alpine:latest',
    command: 'sh -c "echo hello > /workspace/output.txt"',
    cpu: 0.5,
    memory: 512,
    timeoutSeconds: 300,
    artifacts: [
        new DownloadArtifact('source', 'https://example.com/source.tar.gz', 'code.tar.gz'),
        new UploadArtifact('result', 'output.txt', 'https://example.com/upload', depends: 'job', chunked: true),
    ],
    callback: new Callback(
        url: 'https://app.example.com/orchestrator/events',
        events: [CallbackEvent::Log, CallbackEvent::Artifact, CallbackEvent::Exit],
        key: 'webhook-secret',
    ),
);
```

## Jobs

```php
$created = $jobs->create(id: 'build-001', image: 'alpine:latest', command: 'echo hello');
$status = $jobs->get('build-001');
$list = $jobs->list();
$jobs->delete('build-001');
```

## Errors

API responses with status `>= 400` throw `ApiException` with `statusCode`, raw `body`, and decoded JSON when available.

```php
use OpenRuntimes\Orchestrator\Exception\ApiException;

try {
    $jobs->get('missing');
} catch (ApiException $e) {
    echo $e->statusCode;
    echo $e->getMessage();
}
```

## Callback Signatures

```php
use OpenRuntimes\Orchestrator\Callback\Signature;

$valid = Signature::verifyEvent($rawBody, $headers['x-signature-256'] ?? '', $secret);
```

## Development

```sh
composer install
composer test
composer analyze
composer format:check
composer refactor:check
```
