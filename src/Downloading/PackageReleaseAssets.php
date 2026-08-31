<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Composer\Util\HttpDownloader;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Platform\TargetPlatform;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
interface PackageReleaseAssets
{
    /** @param non-empty-list<non-empty-string> $possibleReleaseAssetNames */
    public function findMatchingReleaseAsset(
        TargetPlatform $targetPlatform,
        Package $package,
        HttpDownloader $httpDownloader,
        DownloadUrlMethod $downloadUrlMethod,
        array $possibleReleaseAssetNames,
    ): MatchedReleaseAsset;
}
