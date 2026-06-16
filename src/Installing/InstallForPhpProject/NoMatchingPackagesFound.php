<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Php\Pie\ExtensionName;
use RuntimeException;

use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class NoMatchingPackagesFound extends RuntimeException
{
    public static function forExtension(ExtensionName $extensionName): self
    {
        return new self(sprintf(
            'PIE could not find any potential matches for %s; if you know which package to use, specify --select=vendor/package in the `pie install` options.',
            $extensionName->nameWithExtPrefix(),
        ));
    }
}
