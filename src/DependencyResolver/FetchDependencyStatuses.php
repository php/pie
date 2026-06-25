<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Composer;
use Composer\Package\CompletePackageInterface;
use Composer\Semver\Constraint\Constraint;
use Php\Pie\ComposerIntegration\PhpBinaryPathBasedPlatformRepository;
use Php\Pie\Platform\InstalledPiePackages;
use Php\Pie\Platform\TargetPlatform;

use function array_key_exists;
use function count;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class FetchDependencyStatuses
{
    /** @return list<DependencyStatus> */
    public function __invoke(TargetPlatform $targetPlatform, Composer $composer, CompletePackageInterface $package): array
    {
        $requires = $package->getRequires();

        if (count($requires) <= 0) {
            return [];
        }

        /** @var array<string, Constraint> $platformConstraints */
        $platformConstraints = [];
        $composerPlatform    = new PhpBinaryPathBasedPlatformRepository($targetPlatform->phpBinaryPath, $composer, new InstalledPiePackages(), []);
        foreach ($composerPlatform->getPackages() as $platformPackage) {
            $platformConstraints[$platformPackage->getName()] = new Constraint('==', $platformPackage->getVersion());
        }

        $checkedPackages = [];

        foreach ($requires as $requireName => $requireLink) {
            $checkedPackages[] = new DependencyStatus(
                $requireName,
                $requireLink->getConstraint(),
                array_key_exists($requireName, $platformConstraints) ? $platformConstraints[$requireName] : null,
            );
        }

        return $checkedPackages;
    }
}
