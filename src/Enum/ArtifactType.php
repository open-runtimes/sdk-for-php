<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Enum;

enum ArtifactType: string
{
    case Download = 'download';
    case Upload = 'upload';
    case Write = 'write';
    case Read = 'read';
    case Archive = 'archive';
    case Unarchive = 'unarchive';
    case List = 'list';
    case Stat = 'stat';
}
