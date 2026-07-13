<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Enum;

enum CallbackEvent: string
{
    case Start = 'orchestrator.job.start';
    case Artifact = 'orchestrator.job.artifact';
    case Log = 'orchestrator.job.log';
    case Exit = 'orchestrator.job.exit';
    case Complete = 'orchestrator.job.complete';
}
