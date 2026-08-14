<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Enum;

enum SandboxState: string
{
    case Creating = 'creating';
    case Ready = 'ready';
    case Failed = 'failed';
    case Deleting = 'deleting';
}
