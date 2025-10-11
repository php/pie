<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Update;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;

use function strtolower;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class ReleaseIsNewer
{
    /**
     * @param non-empty-string $stability
     * @phpstan-param 'stable'|'RC'|'beta'|'alpha'|'dev' $stability
     */
    private static function stabilityToChannel(string $stability): Channel
    {
        return match (strtolower($stability)) {
            'stable' => Channel::Stable,
            'rc', 'beta', 'alpha' => Channel::Preview,
            'dev' => Channel::Nightly,
        };
    }

    /** @param non-empty-string $currentPieVersion */
    public static function forChannel(
        Channel $updateChannel,
        string $currentPieVersion,
        ReleaseMetadata $newRelease,
    ): bool {
        $newVersion   = $newRelease->tag;
        $newStability = self::stabilityToChannel(VersionParser::parseStability($newVersion));

        $currentStability = self::stabilityToChannel(VersionParser::parseStability($currentPieVersion));

        $currentIsStable  = $currentStability === Channel::Stable;
        $currentIsPreview = $currentStability === Channel::Preview;
        $currentIsNightly = $currentStability === Channel::Nightly;
        $newIsStable      = $newStability === Channel::Stable;
        $newIsPreview     = $newStability === Channel::Preview;
        $newIsNightly     = $newStability === Channel::Nightly;

        switch ($updateChannel) {
            case Channel::Stable:
                // Do not upgrade to preview or nightly
                if (! $newIsStable) {
                    return false;
                }

                // If current is nightly/preview, any stable version is an upgrade
                if ($currentIsNightly || $currentIsPreview) {
                    return true;
                }

                return Semver::satisfies($newVersion, '> ' . $currentPieVersion);

            case Channel::Preview:
                // Do not update to a nightly
                if ($newIsNightly) {
                    return false;
                }

                // If current is nightly, allow upgrade to stable/preview
                if ($currentIsNightly) {
                    return true;
                }

                // Compare versions normally for stable/preview
                return Semver::satisfies($newVersion, '> ' . $currentPieVersion);

            case Channel::Nightly:
                // Nightly channel: accept any newer version or nightly builds
                if ($newIsNightly) {
                    // For nightly builds, always update to nightly (or same nightly counts as update)
                    return true;
                }

                return Semver::satisfies($newVersion, '> ' . $currentPieVersion);
        }
    }
}
