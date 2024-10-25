<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\Platform\TargetPlatform;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
final class PieComposerRequest
{
    /** @param list<non-empty-string> $configureOptions */
    public function __construct(
        public readonly OutputInterface $pieOutput,
        public readonly TargetPlatform $targetPlatform,
        public readonly RequestedPackageAndVersion $requestedPackage,
        public readonly PieOperation $operation,
        public readonly array $configureOptions,
    ) {
    }
}
