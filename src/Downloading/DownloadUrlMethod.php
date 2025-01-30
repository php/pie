<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Php\Pie\DependencyResolver\Package;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\PrePackagedSourceAssetName;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\WindowsExtensionAssetName;

enum DownloadUrlMethod
{
    case ComposerDefaultDownload;
    case WindowsBinaryDownload;
    case PrePackagedSourceDownload;

    /** @return non-empty-list<non-empty-string>|null */
    public function possibleAssetNames(Package $package, TargetPlatform $targetPlatform): array|null
    {
        return match ($this) {
            self::WindowsBinaryDownload => WindowsExtensionAssetName::zipNames($targetPlatform, $package),
            self::PrePackagedSourceDownload => PrePackagedSourceAssetName::packageNames($package),
            self::ComposerDefaultDownload => null,
        };
    }

    public static function fromPackage(Package $package, TargetPlatform $targetPlatform): self
    {
        /**
         * PIE does not support building on Windows (yet, at least). Maintainers
         * should provide pre-built Windows binaries.
         */
        if ($targetPlatform->operatingSystem === OperatingSystem::Windows) {
            return self::WindowsBinaryDownload;
        }

        /**
         * Some packages pre-package source code (e.g. mongodb) as there are
         * external dependencies in Git submodules that otherwise aren't
         * included in GitHub/Gitlab/etc "dist" downloads
         */
        if (false) { // @todo check $package `php-ext`
            return self::PrePackagedSourceDownload;
        }

        return self::ComposerDefaultDownload;
    }
}
