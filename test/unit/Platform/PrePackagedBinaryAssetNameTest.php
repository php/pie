<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform;

use Composer\Package\CompletePackageInterface;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\DebugBuild;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\PrePackagedBinaryAssetName;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrePackagedBinaryAssetName::class)]
final class PrePackagedBinaryAssetNameTest extends TestCase
{
    public function testPackageNamesNts(): void
    {
        $php = $this->createMock(PhpBinaryPath::class);
        $php->method('debugMode')->willReturn(DebugBuild::NoDebug);
        $php->method('majorMinorVersion')->willReturn('8.2');

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $php,
            Architecture::x86_64,
            ThreadSafetyMode::NonThreadSafe,
            1,
            null,
        );

        $libc = $targetPlatform->libcFlavour();
        self::assertSame(
            [
                'php_foobar-1.2.3_php8.2-x86_64-linux-' . $libc->value . '.zip',
                'php_foobar-1.2.3_php8.2-x86_64-linux-' . $libc->value . '.tgz',
                'php_foobar-1.2.3_php8.2-x86_64-linux-' . $libc->value . '-nts.zip',
                'php_foobar-1.2.3_php8.2-x86_64-linux-' . $libc->value . '-nts.tgz',
                'php_foobar-1.2.3_php8.2-x64-linux-' . $libc->value . '.zip',
                'php_foobar-1.2.3_php8.2-x64-linux-' . $libc->value . '.tgz',
                'php_foobar-1.2.3_php8.2-x64-linux-' . $libc->value . '-nts.zip',
                'php_foobar-1.2.3_php8.2-x64-linux-' . $libc->value . '-nts.tgz',
            ],
            PrePackagedBinaryAssetName::packageNames(
                $targetPlatform,
                new Package(
                    $this->createMock(CompletePackageInterface::class),
                    ExtensionType::PhpModule,
                    ExtensionName::normaliseFromString('foobar'),
                    'foo/bar',
                    '1.2.3',
                    null,
                ),
            ),
        );
    }

    public function testPackageNamesZts(): void
    {
        $php = $this->createMock(PhpBinaryPath::class);
        $php->method('debugMode')->willReturn(DebugBuild::NoDebug);
        $php->method('majorMinorVersion')->willReturn('8.3');

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $php,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
        );

        $libc = $targetPlatform->libcFlavour();
        self::assertSame(
            [
                'php_foobar-1.2.3_php8.3-x86_64-linux-' . $libc->value . '-zts.zip',
                'php_foobar-1.2.3_php8.3-x86_64-linux-' . $libc->value . '-zts.tgz',
                'php_foobar-1.2.3_php8.3-x64-linux-' . $libc->value . '-zts.zip',
                'php_foobar-1.2.3_php8.3-x64-linux-' . $libc->value . '-zts.tgz',
            ],
            PrePackagedBinaryAssetName::packageNames(
                $targetPlatform,
                new Package(
                    $this->createMock(CompletePackageInterface::class),
                    ExtensionType::PhpModule,
                    ExtensionName::normaliseFromString('foobar'),
                    'foo/bar',
                    '1.2.3',
                    null,
                ),
            ),
        );
    }

    public function testPackageNamesDebug(): void
    {
        $php = $this->createMock(PhpBinaryPath::class);
        $php->method('debugMode')->willReturn(DebugBuild::Debug);
        $php->method('majorMinorVersion')->willReturn('8.4');

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Darwin,
            $php,
            Architecture::arm64,
            ThreadSafetyMode::NonThreadSafe,
            1,
            null,
        );

        $libc = $targetPlatform->libcFlavour();
        self::assertSame(
            [
                'php_foobar-1.2.3_php8.4-arm64-darwin-' . $libc->value . '-debug.zip',
                'php_foobar-1.2.3_php8.4-arm64-darwin-' . $libc->value . '-debug.tgz',
                'php_foobar-1.2.3_php8.4-arm64-darwin-' . $libc->value . '-debug-nts.zip',
                'php_foobar-1.2.3_php8.4-arm64-darwin-' . $libc->value . '-debug-nts.tgz',
                'php_foobar-1.2.3_php8.4-aarch64-darwin-' . $libc->value . '-debug.zip',
                'php_foobar-1.2.3_php8.4-aarch64-darwin-' . $libc->value . '-debug.tgz',
                'php_foobar-1.2.3_php8.4-aarch64-darwin-' . $libc->value . '-debug-nts.zip',
                'php_foobar-1.2.3_php8.4-aarch64-darwin-' . $libc->value . '-debug-nts.tgz',
            ],
            PrePackagedBinaryAssetName::packageNames(
                $targetPlatform,
                new Package(
                    $this->createMock(CompletePackageInterface::class),
                    ExtensionType::PhpModule,
                    ExtensionName::normaliseFromString('foobar'),
                    'foo/bar',
                    '1.2.3',
                    null,
                ),
            ),
        );
    }
}
