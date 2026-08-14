<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Enum;

enum DeploymentState: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Idle = 'idle';
    case Degraded = 'degraded';
    case Failed = 'failed';
    case Deleting = 'deleting';
}
