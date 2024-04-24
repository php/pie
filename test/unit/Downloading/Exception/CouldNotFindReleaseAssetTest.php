<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading\Exception;

use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\Exception\CouldNotFindReleaseAsset;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CouldNotFindReleaseAsset::class)]
final class CouldNotFindReleaseAssetTest extends TestCase
{
    public function testForPackage(): void
    {
        $package = new Package('foo/bar', '1.2.3', null);

        $exception = CouldNotFindReleaseAsset::forPackage($package, 'something.zip');

        self::assertSame('Could not find release asset for foo/bar:1.2.3 named "something.zip"', $exception->getMessage());
    }

    public function testForPackageWithMissingTag(): void
    {
        $package = new Package('foo/bar', '1.2.3', null);

        $exception = CouldNotFindReleaseAsset::forPackageWithMissingTag($package);

        self::assertSame('Could not find release by tag name for foo/bar:1.2.3', $exception->getMessage());
    }
}
