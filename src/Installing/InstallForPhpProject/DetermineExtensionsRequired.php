<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Composer\Package\Link;
use Composer\Package\RootPackageInterface;
use Php\Pie\ExtensionName;

use function array_filter;
use function array_merge;
use function str_starts_with;
use function strlen;
use function substr;

class DetermineExtensionsRequired
{
    /** @return array<string, Link> */
    public function forProject(RootPackageInterface $rootPackage): array
    {
        return array_filter(
            array_merge($rootPackage->getRequires(), $rootPackage->getDevRequires()),
            static function (Link $link) {
                $linkTarget = $link->getTarget();
                if (! str_starts_with($linkTarget, 'ext-')) {
                    return false;
                }

                return ExtensionName::isValidExtensionName(substr($linkTarget, strlen('ext-')));
            },
        );
    }
}
