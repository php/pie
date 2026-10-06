<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Package\CompletePackageInterface;
use Composer\Repository\PlatformRepository;
use Composer\Semver\Constraint\Constraint;

use function array_key_exists;
use function count;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class FetchDependencyStatuses
{
    /** @return list<DependencyStatus> */
    public function __invoke(PlatformRepository $platformRepository, CompletePackageInterface $package): array
    {
        $requires = $package->getRequires();

        if (count($requires) <= 0) {
            return [];
        }

        /** @var array<string, Constraint> $platformConstraints */
        $platformConstraints = [];
        foreach ($platformRepository->getPackages() as $platformPackage) {
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
