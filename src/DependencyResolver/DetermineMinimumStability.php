<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Semver\VersionParser;
use Webmozart\Assert\Assert;

/**
 * Utility to extract a valid Stability (see {@see \Composer\Package\BasePackage::$stabilities}) from a requested
 * package version in a predictable way.
 *
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 */
final class DetermineMinimumStability
{
    private const STABILITY_STABLE = 'stable';
    private const STABILITY_RC     = 'RC';
    private const STABILITY_BETA   = 'beta';
    private const STABILITY_ALPHA  = 'alpha';
    private const STABILITY_DEV    = 'dev';

    private const DEFAULT_MINIMUM_STABILITY = self::STABILITY_STABLE;

    /** @psalm-assert self::STABILITY_* $stability */
    private static function assertValidStabilityString(string $stability): void
    {
        Assert::oneOf(
            $stability,
            [
                self::STABILITY_STABLE,
                self::STABILITY_RC,
                self::STABILITY_BETA,
                self::STABILITY_ALPHA,
                self::STABILITY_DEV,
            ],
        );
    }

    /** @return self::STABILITY_* */
    public static function fromRequestedVersion(string|null $requestedVersion): string
    {
        if ($requestedVersion === null) {
            return self::DEFAULT_MINIMUM_STABILITY;
        }

        $stability = VersionParser::parseStability($requestedVersion);
        self::assertValidStabilityString($stability);

        return $stability;
    }
}
