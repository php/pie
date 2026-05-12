<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

use Countable;
use OutOfRangeException;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Util\PackageVerificationStatus;
use Webmozart\Assert\Assert;

use function array_filter;
use function array_key_first;
use function array_values;
use function count;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class PiePackageList implements Countable
{
    /** @param list<Package> $piePackages */
    public function __construct(private readonly array $piePackages)
    {
    }

    public function findByPhpFormattedExtensionName(string $phpFormattedExtensionName): PiePackageList
    {
        return new self(array_values(array_filter(
            $this->piePackages,
            static fn (Package $piePackage) => $piePackage->extensionName()->phpFormattedExtensionName() === $phpFormattedExtensionName,
        )));
    }

    public function findByPackageName(string $packageName): Package
    {
        foreach ($this->piePackages as $piePackage) {
            if ($piePackage->name() === $packageName) {
                return $piePackage;
            }
        }

        throw new OutOfRangeException('Package ' . $packageName . ' not in the list');
    }

    public function onlyVerifiedFor(TargetPlatform $targetPlatform): self
    {
        return new self(array_values(array_filter(
            $this->piePackages,
            static fn (Package $piePackage) => $piePackage->verifyPackageStatus($targetPlatform) === PackageVerificationStatus::Verified,
        )));
    }

    public function onlyOne(): Package
    {
        Assert::count($this->piePackages, 1);

        return $this->piePackages[array_key_first($this->piePackages)];
    }

    /** @return list<Package> */
    public function packages(): array
    {
        return $this->piePackages;
    }

    public function count(): int
    {
        return count($this->piePackages);
    }
}
