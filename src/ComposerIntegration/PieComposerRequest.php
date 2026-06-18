<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\IO\IOInterface;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\Platform\TargetPlatform;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
final class PieComposerRequest
{
    /**
     * @param list<RequestedPackageAndVersion> $requestedPackages
     * @param list<non-empty-string>           $configureOptions
     */
    public function __construct(
        public readonly IOInterface $pieOutput,
        public readonly TargetPlatform $targetPlatform,
        public readonly array $requestedPackages,
        public readonly PieOperation $operation,
        public readonly array $configureOptions,
        public readonly bool $attemptToSetupIniFile,
    ) {
    }

    /**
     * Useful for when we don't want to perform any "write" style operations;
     * for example just reading metadata about the installed system.
     */
    public static function noOperation(
        IOInterface $pieOutput,
        TargetPlatform $targetPlatform,
    ): self {
        return new PieComposerRequest(
            $pieOutput,
            $targetPlatform,
            [],
            PieOperation::Resolve,
            [],
            false,
        );
    }
}
