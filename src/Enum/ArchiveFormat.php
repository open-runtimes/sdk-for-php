<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Enum;

enum ArchiveFormat: string
{
    case Tar = 'tar';
    case Squashfs = 'squashfs';
    case Erofs = 'erofs';
}
