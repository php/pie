<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

use Countable;
use OutOfRangeException;
use Php\Pie\DependencyResolver\Package;

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
        $matched = [];
        foreach ($this->piePackages as $piePackage) {
            if ($piePackage->extensionName()->phpFormattedExtensionName() !== $phpFormattedExtensionName) {
                continue;
            }

            $matched[] = $piePackage;
        }

        return new self($matched);
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
