<?php
declare(strict_types=1);

namespace ScriptFUSION\Steam250\Shared\Storage;

use Eloquent\Enumeration\AbstractEnumeration;

/**
 * @method static READ_DIR()
 * @method static WRITE_DIR()
 */
final class StorageRoot extends AbstractEnumeration
{
    public const READ_DIR = 'data';
    public const WRITE_DIR = 'building...';

    private static $nameMapping = [
        self::READ_DIR => 'read',
        self::WRITE_DIR => 'write',
    ];

    public function getName(): string
    {
        return self::$nameMapping[$this->value()];
    }

    public function getDirectory(): string
    {
        return $this->value();
    }
}
