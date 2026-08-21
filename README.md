# Orchestrator PHP Client

PHP SDK for the Open Runtimes orchestrator: jobs, deployments, sandboxes, and pools.

Server: https://github.com/open-runtimes/orchestrator

Each service is its own client over one configured `Utopia\Client`:

```php
$jobs        = new Jobs($http);
$deployments = new Deployments($http);
$sandboxes   = new Sandboxes($http);
$pools       = new DeploymentPools($http);
```

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
        new UploadArtifact('result', 'output.txt', 'https://example.com/upload', depends: 'job'),
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

## Deployments

A deployment is a container serving HTTP behind the orchestrator's gateway, kept
running, routable, and scaled — including down to zero. `apply()` is declarative:
applying a changed spec for an existing id rolls out a new revision, and applying
an identical one is a no-op.

```php
use OpenRuntimes\Orchestrator\Deployments;
use OpenRuntimes\Orchestrator\Model\Autoscaling;
use OpenRuntimes\Orchestrator\Model\Probe;
use OpenRuntimes\Orchestrator\Model\Probes;

$deployments = new Deployments($http);

$web = $deployments->apply(
    id: 'web',
    image: 'ghcr.io/acme/web:v3',
    port: 8080,
    hosts: ['acme.com', 'www.acme.com'],
    autoscaling: new Autoscaling(minReplicas: 0, maxReplicas: 10, target: 100),
    probes: new Probes(readiness: new Probe(path: '/healthz', periodMillis: 500)),
);

echo $web->url;               // primary host
echo $web->status->value;     // pending|ready|idle|degraded|failed|deleting

$deployments->get('web');
$deployments->list();
$deployments->delete('web');
```

### Traffic

Every spec change mints an immutable revision, which makes canaries and rollbacks
cheap. Pinning any split switches the deployment to manual mode; `release()` hands
traffic back to auto.

```php
use OpenRuntimes\Orchestrator\Model\TrafficTarget;

$revisions = $deployments->revisions('web');

// Canary: 90% stable, 10% new.
$deployments->setTraffic('web', [
    new TrafficTarget('web-00001', 90),
    new TrafficTarget('web-00002', 10),
]);

// Rollback is just a split.
$deployments->setTraffic('web', [new TrafficTarget('web-00001', 100)]);

// Back to auto: 100% on the latest revision, auto-cut re-armed.
$deployments->release('web');
```

## Sandboxes

A sandbox is a live, isolated workspace you drive from the outside. Name a `pool`
to claim an already-running pod (sub-second), or an `image` to have one built for
this request (a cold start, but nothing to configure ahead of time and per-sandbox
control over `cpu`, `memory`, `runtimeClass`, and `volumes`).

```php
use OpenRuntimes\Orchestrator\Enum\RuntimeClass;
use OpenRuntimes\Orchestrator\Sandboxes;

$sandboxes = new Sandboxes($http);

$sandbox = $sandboxes->create(
    pool: 'py',
    id: 'agent-run-42',
    ports: [5173],                 // extra ports, each at its own hostname
    timeoutSeconds: 0,             // no per-request bound, for long-lived sessions
    idleTimeoutSeconds: 900,
    artifacts: [
        new DownloadArtifact('code', 'https://acme.test/app.tar.gz', 'app.tar.gz'),
        new UnarchiveArtifact('unpack', 'app.tar.gz', '.', depends: 'code'),
    ],
);

// Without a pool — a port is required, since nothing else declares one.
$sandboxes->create(image: 'python:3.12-slim', port: 3000, runtimeClass: RuntimeClass::Gvisor);

$sandboxes->get('agent-run-42');
$sandboxes->list();
$sandboxes->delete('agent-run-42');   // invalidates the URL immediately

$sandboxes->pools();                  // read-only: pools are operator config
```

Running commands and moving files are **not** part of this API. They are an HTTP
contract (`POST /execute`, `GET|PUT|DELETE /files/{path}`) served *inside* the
sandbox, at the address in `$sandbox->url` — read secondary ports out of
`$sandbox->urls` rather than building them.

**Treat those URLs as secrets.** Reaching one is sufficient to run commands in the
sandbox, which is why the hostname carries an unguessable token instead of the id.

A read tells you what the sandbox is, not just where it is: `$sandbox->image`,
`$sandbox->cpu`, and `$sandbox->memory` are the shape it is running in, recorded
when its pod was created, so you never have to keep a record of what you asked
for. They are null for a sandbox created before the orchestrator recorded it.

A sandbox that fails to materialize is not an error response: `create()` returns a
status with `SandboxState::Failed` and an `error`, because the sandbox exists as a
record you can read and delete.

## Deployment pools

A pool is standing warm capacity; an activation claims one warm pod and late-binds
your payload onto it. Pools are operator configuration, so the API over them is
read plus activate.

```php
use OpenRuntimes\Orchestrator\DeploymentPools;

$pools = new DeploymentPools($http);

$pools->list();
$pools->get('node');

$activation = $pools->activate(
    poolId: 'node',
    command: 'node server.js',
    id: 'preview-7',            // choosing one buys idempotency
    idleTimeoutSeconds: 600,
);

echo $activation->url;

$pools->activations('node');
$pools->activation('node', 'preview-7');
$pools->deactivate('node', 'preview-7');
```

Pass `async: true` to get an accepted activation back immediately, with the result
delivered to your callback as an `orchestrator.pool.activation.result` event. It
requires a callback — nothing is stored to poll in the meantime — and the returned
activation has no `id` yet.

```php
$pools->activate(
    poolId: 'node',
    command: 'node server.js',
    callback: new Callback(
        url: 'https://acme.test/hook',
        events: [CallbackEvent::PoolActivationResult],
        key: 'signing-secret',
    ),
    async: true,
);
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
