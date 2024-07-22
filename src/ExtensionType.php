<?php

declare(strict_types=1);

namespace Php\Pie;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
enum ExtensionType: string
{
    case PhpModule     = 'php-ext';
    case ZendExtension = 'php-ext-zend';

    public static function isValid(string $toBeChecked): bool
    {
        return self::tryFrom($toBeChecked) !== null;
    }
}
