<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Enum;

enum ActivationState: string
{
    case Activating = 'activating';
    case Ready = 'ready';
    case Failed = 'failed';
    case Deactivating = 'deactivating';
}
