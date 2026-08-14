<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Enum;

enum RuntimeClass: string
{
    case Runc = 'runc';
    case Gvisor = 'gvisor';
    case Kata = 'kata';
}
