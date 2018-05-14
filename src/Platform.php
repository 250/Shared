<?php
declare(strict_types=1);

namespace ScriptFUSION\Steam250\Shared;

use ScriptFUSION\StaticClass;

final class Platform
{
    use StaticClass;

    public const WINDOWS = 1 << 0;
    public const LINUX   = 1 << 1;
    public const MAC     = 1 << 2;
    public const VIVE    = 1 << 3;
    public const OCULUS  = 1 << 4;
    public const WMR     = 1 << 5;
}
