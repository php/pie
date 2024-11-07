<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading\Exception;

use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\Exception\CouldNotFindReleaseAsset;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CouldNotFindReleaseAsset::class)]
final class CouldNotFindReleaseAssetTest extends TestCase
{
    public function testForPackage(): void
    {
        $package = new Package(
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'foo/bar',
            '1.2.3',
            null,
            [],
            null,
            '1.2.3.0',
            true,
            true,
            '',
        );

        $exception = CouldNotFindReleaseAsset::forPackage($package, ['something.zip', 'something2.zip']);

        self::assertSame('Could not find release asset for foo/bar:1.2.3 named one of "something.zip, something2.zip"', $exception->getMessage());
    }

    public function testForPackageWithMissingTag(): void
    {
        $package = new Package(
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'foo/bar',
            '1.2.3',
            null,
            [],
            null,
            '1.2.3.0',
            true,
            true,
            '',
        );

        $exception = CouldNotFindReleaseAsset::forPackageWithMissingTag($package);

        self::assertSame('Could not find release by tag name for foo/bar:1.2.3', $exception->getMessage());
    }

    public function testForMissingWindowsCompiler(): void
    {
        $phpBinary = PhpBinaryPath::fromCurrentProcess();
        $exception = CouldNotFindReleaseAsset::forMissingWindowsCompiler(new TargetPlatform(
            OperatingSystem::NonWindows,
            $phpBinary,
            Architecture::x86,
            ThreadSafetyMode::NonThreadSafe,
            1,
            null,
        ));

        self::assertSame('Could not determine Windows Compiler for PHP ' . $phpBinary->version() . ' on NonWindows', $exception->getMessage());
    }
}
