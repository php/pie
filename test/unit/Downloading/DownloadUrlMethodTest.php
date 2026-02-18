<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading;

use Composer\Package\CompletePackage;
use Composer\Package\CompletePackageInterface;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Downloading\DownloadUrlMethod;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\DebugBuild;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use Php\Pie\Platform\WindowsCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ValueError;

use function array_key_first;

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

        $downloadUrlMethods = DownloadUrlMethod::possibleDownloadUrlMethodsForPackage($package, $targetPlatform);

        self::assertCount(1, $downloadUrlMethods);
        $downloadUrlMethod = $downloadUrlMethods[array_key_first($downloadUrlMethods)];

        self::assertSame(DownloadUrlMethod::WindowsBinaryDownload, $downloadUrlMethod);

        self::assertSame(
            [
                'php_foo-1.2.3-8.1-nts-vc15-x86_64.zip',
                'php_foo-1.2.3-8.1-vc15-nts-x86_64.zip',
                'php_foo-1.2.3-8.1-nts-vc15-x64.zip',
                'php_foo-1.2.3-8.1-vc15-nts-x64.zip',
            ],
            $downloadUrlMethod->possibleAssetNames($package, $targetPlatform),
        );
    }

    public function testPrePackagedSourceDownloads(): void
    {
        $composerPackage = $this->createMock(CompletePackageInterface::class);
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

        $downloadUrlMethods = DownloadUrlMethod::possibleDownloadUrlMethodsForPackage($package, $targetPlatform);

        self::assertCount(1, $downloadUrlMethods);
        $downloadUrlMethod = $downloadUrlMethods[array_key_first($downloadUrlMethods)];

        self::assertSame(DownloadUrlMethod::PrePackagedSourceDownload, $downloadUrlMethod);

        self::assertSame(
            [
                'php_bar-1.2.3-src.tgz',
                'php_bar-1.2.3-src.zip',
                'bar-1.2.3.tgz',
            ],
            $downloadUrlMethod->possibleAssetNames($package, $targetPlatform),
        );
    }

    public function testPrePackagedBinaryDownloads(): void
    {
        $composerPackage = $this->createMock(CompletePackageInterface::class);
        $composerPackage->method('getPrettyName')->willReturn('foo/bar');
        $composerPackage->method('getPrettyVersion')->willReturn('1.2.3');
        $composerPackage->method('getType')->willReturn('php-ext');
        $composerPackage->method('getPhpExt')->willReturn(['download-url-method' => ['pre-packaged-binary']]);

        $package = Package::fromComposerCompletePackage($composerPackage);

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath
            ->method('majorMinorVersion')
            ->willReturn('8.3');
        $phpBinaryPath
            ->method('debugMode')
            ->willReturn(DebugBuild::Debug);

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
        );

        $downloadUrlMethods = DownloadUrlMethod::possibleDownloadUrlMethodsForPackage($package, $targetPlatform);

        self::assertCount(1, $downloadUrlMethods);
        $downloadUrlMethod = $downloadUrlMethods[array_key_first($downloadUrlMethods)];

        self::assertSame(DownloadUrlMethod::PrePackagedBinary, $downloadUrlMethod);

        self::assertSame(
            [
                'php_bar-1.2.3_php8.3-x86_64-linux-glibc-debug-zts.zip',
                'php_bar-1.2.3_php8.3-x86_64-linux-glibc-debug-zts.tgz',
                'php_bar-1.2.3_php8.3-x64-linux-glibc-debug-zts.zip',
                'php_bar-1.2.3_php8.3-x64-linux-glibc-debug-zts.tgz',
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

        $downloadUrlMethods = DownloadUrlMethod::possibleDownloadUrlMethodsForPackage($package, $targetPlatform);

        self::assertCount(1, $downloadUrlMethods);
        $downloadUrlMethod = $downloadUrlMethods[array_key_first($downloadUrlMethods)];

        self::assertSame(DownloadUrlMethod::ComposerDefaultDownload, $downloadUrlMethod);

        self::assertNull($downloadUrlMethod->possibleAssetNames($package, $targetPlatform));
    }

    public function testMultipleDownloadUrlMethods(): void
    {
        $composerPackage = $this->createMock(CompletePackageInterface::class);
        $composerPackage->method('getPrettyName')->willReturn('foo/bar');
        $composerPackage->method('getPrettyVersion')->willReturn('1.2.3');
        $composerPackage->method('getType')->willReturn('php-ext');
        $composerPackage->method('getPhpExt')->willReturn(['download-url-method' => ['pre-packaged-binary', 'pre-packaged-source', 'composer-default']]);

        $package = Package::fromComposerCompletePackage($composerPackage);

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath
            ->method('majorMinorVersion')
            ->willReturn('8.3');
        $phpBinaryPath
            ->method('debugMode')
            ->willReturn(DebugBuild::Debug);

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::NonThreadSafe,
            1,
            null,
        );

        $downloadUrlMethods = DownloadUrlMethod::possibleDownloadUrlMethodsForPackage($package, $targetPlatform);

        self::assertCount(3, $downloadUrlMethods);

        $firstMethod = $downloadUrlMethods[0];
        self::assertSame(DownloadUrlMethod::PrePackagedBinary, $firstMethod);
        self::assertSame(
            [
                'php_bar-1.2.3_php8.3-x86_64-linux-glibc-debug.zip',
                'php_bar-1.2.3_php8.3-x86_64-linux-glibc-debug.tgz',
                'php_bar-1.2.3_php8.3-x86_64-linux-glibc-debug-nts.zip',
                'php_bar-1.2.3_php8.3-x86_64-linux-glibc-debug-nts.tgz',
                'php_bar-1.2.3_php8.3-x64-linux-glibc-debug.zip',
                'php_bar-1.2.3_php8.3-x64-linux-glibc-debug.tgz',
                'php_bar-1.2.3_php8.3-x64-linux-glibc-debug-nts.zip',
                'php_bar-1.2.3_php8.3-x64-linux-glibc-debug-nts.tgz',
            ],
            $firstMethod->possibleAssetNames($package, $targetPlatform),
        );

        $secondMethod = $downloadUrlMethods[1];
        self::assertSame(DownloadUrlMethod::PrePackagedSourceDownload, $secondMethod);
        self::assertSame(
            [
                'php_bar-1.2.3-src.tgz',
                'php_bar-1.2.3-src.zip',
                'bar-1.2.3.tgz',
            ],
            $secondMethod->possibleAssetNames($package, $targetPlatform),
        );

        $thirdMethod = $downloadUrlMethods[2];
        self::assertSame(DownloadUrlMethod::ComposerDefaultDownload, $thirdMethod);
        self::assertNull($thirdMethod->possibleAssetNames($package, $targetPlatform));
    }

    public function testFromComposerPackageWhenPackageKeyWasDefined(): void
    {
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        DownloadUrlMethod::PrePackagedBinary->writeToComposerPackage($composerPackage);
        self::assertSame(DownloadUrlMethod::PrePackagedBinary, DownloadUrlMethod::fromComposerPackage($composerPackage));
    }

    public function testFromComposerPackageWhenPackageKeyWasNotDefined(): void
    {
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');

        $this->expectException(ValueError::class);
        DownloadUrlMethod::fromComposerPackage($composerPackage);
    }

    public function testFromDownloadedPackage(): void
    {
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        DownloadUrlMethod::PrePackagedSourceDownload->writeToComposerPackage($composerPackage);

        $downloaded = DownloadedPackage::fromPackageAndExtractedPath(
            new Package(
                $composerPackage,
                ExtensionType::PhpModule,
                ExtensionName::normaliseFromString('foo'),
                'foo/bar',
                '1.2.3',
                null,
            ),
            '/path/to/extracted/source',
        );

        self::assertSame(DownloadUrlMethod::PrePackagedSourceDownload, DownloadUrlMethod::fromDownloadedPackage($downloaded));
    }
}
