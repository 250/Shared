<?php
declare(strict_types=1);

namespace ScriptFUSION\Top250\Shared;

use ScriptFUSION\StaticClass;

final class Platform
{
    use StaticClass;

    public const WINDOWS = 1 << 0;
    public const LINUX   = 1 << 1;
    public const MAC     = 1 << 2;
}
