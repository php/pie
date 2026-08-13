<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration\Listeners;

use Php\Pie\DependencyResolver\Package;
use RuntimeException;

use function sprintf;

class AllDownloadUrlMethodsSuppressed extends RuntimeException
{
    public static function forPackage(Package $piePackage): self
    {
        return new self(sprintf(
            'Could not find a way to download %s as all possible download URL methods were suppressed',
            $piePackage->name(),
        ));
    }
}
