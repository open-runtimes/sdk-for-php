<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Enum;

enum JobState: string
{
    case Accepted = 'accepted';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
