<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Enum;

enum ArchiveCompression: string
{
    case None = 'none';
    case Gzip = 'gzip';
    case Zstd = 'zstd';
    case Lz4 = 'lz4';
}
