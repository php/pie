<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use RuntimeException;

use function sprintf;

use const PHP_EOL;

class BundledPhpExtensionRefusal extends RuntimeException
{
    public static function forPackage(Package $package): self
    {
        return new self(sprintf(
            'Bundled PHP extension %s should be installed by your distribution, not by PIE.%s%sCombining installation methods of bundled PHP extensions can lead to confusing and unintended consequences.%s%sIf you are really sure, you want to install %s using PIE, re-run the command with the --force flag.',
            $package->name(),
            PHP_EOL,
            PHP_EOL,
            PHP_EOL,
            PHP_EOL,
            $package->name(),
        ));
    }
}
