<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading;

use Composer\Package\CompletePackage;
use Composer\Package\CompletePackageInterface;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadUrlMethod;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use Php\Pie\Platform\WindowsCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DownloadUrlMethod::class)]
final class DownloadUrlMethodTest extends TestCase
{
    public function testWindowsPackages(): void
    {
        $package = new Package(
            $this->createMock(CompletePackageInterface::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'foo/foo',
            '1.2.3',
            null,
        );

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath
            ->method('majorMinorVersion')
            ->willReturn('8.1');

        $targetPlatform = new TargetPlatform(
            OperatingSystem::Windows,
            OperatingSystemFamily::Windows,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::NonThreadSafe,
            1,
            WindowsCompiler::VC15,
        );

        $downloadUrlMethod = DownloadUrlMethod::fromPackage($package, $targetPlatform);

        self::assertSame(DownloadUrlMethod::WindowsBinaryDownload, $downloadUrlMethod);

        self::assertSame(
            [
                'php_foo-1.2.3-8.1-nts-vc15-x86_64.zip',
                'php_foo-1.2.3-8.1-vc15-nts-x86_64.zip',
            ],
            $downloadUrlMethod->possibleAssetNames($package, $targetPlatform),
        );
    }

    public function testPrePackagedSourceDownloads(): void
    {
        $composerPackage = $this->createMock(CompletePackage::class);
        $composerPackage->method('getPrettyName')->willReturn('foo/bar');
        $composerPackage->method('getPrettyVersion')->willReturn('1.2.3');
        $composerPackage->method('getType')->willReturn('php-ext');
        $composerPackage->method('getPhpExt')->willReturn(['download-url-method' => 'pre-packaged-source']);

        $package = Package::fromComposerCompletePackage($composerPackage);

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            PhpBinaryPath::fromCurrentProcess(),
            Architecture::x86_64,
            ThreadSafetyMode::NonThreadSafe,
            1,
            null,
        );

        $downloadUrlMethod = DownloadUrlMethod::fromPackage($package, $targetPlatform);

        self::assertSame(DownloadUrlMethod::PrePackagedSourceDownload, $downloadUrlMethod);

        self::assertSame(
            [
                'php_bar-1.2.3-src.tgz',
                'php_bar-1.2.3-src.zip',
            ],
            $downloadUrlMethod->possibleAssetNames($package, $targetPlatform),
        );
    }

    public function testComposerDefaultDownload(): void
    {
        $package = new Package(
            $this->createMock(CompletePackageInterface::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'foo/foo',
            '1.2.3',
            null,
        );

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            PhpBinaryPath::fromCurrentProcess(),
            Architecture::x86_64,
            ThreadSafetyMode::NonThreadSafe,
            1,
            null,
        );

        $downloadUrlMethod = DownloadUrlMethod::fromPackage($package, $targetPlatform);

        self::assertSame(DownloadUrlMethod::ComposerDefaultDownload, $downloadUrlMethod);

        self::assertNull($downloadUrlMethod->possibleAssetNames($package, $targetPlatform));
    }
}
