<?php
declare(strict_types=1);

namespace ScriptFUSION\Top250\Shared;

use Eloquent\Enumeration\AbstractEnumeration;

/**
 * @method static WILSON()
 * @method static BAYESIAN()
 */
final class Algorithm extends AbstractEnumeration
{
    public const WILSON = 'WILSON';
    public const BAYESIAN = 'BAYESIAN';
}
