<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing;

use Composer\Package\CompletePackageInterface;
use Composer\Util\Filesystem;
use Php\Pie\ComposerIntegration\PieInstalledJsonMetadataKeys;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\PackageMetadataMissing;
use Php\Pie\Installing\UninstallUsingUnlink;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;
use function hash_file;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

#[CoversClass(UninstallUsingUnlink::class)]
final class UninstallUsingUnlinkTest extends TestCase
{
    public function testMissingMetadataThrowsException(): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('extensionPath')
            ->willReturn('/foo/bar');

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
        );

        $composerPackage = $this->createMock(CompletePackageInterface::class);
        $composerPackage
            ->method('getExtra')
            ->willReturn([]);

        $package = new Package(
            $composerPackage,
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foobar'),
            'foobar/foobar',
            '1.2.3',
            null,
        );

        $this->expectException(PackageMetadataMissing::class);
        $this->expectExceptionMessage('PIE metadata was missing for package foobar/foobar. Missing metadata keys: pie-installed-binary, pie-installed-binary-checksum');
        (new UninstallUsingUnlink())($targetPlatform, $package);
    }

    public function testBinaryFileIsRemoved(): void
    {
        $fakeExtensionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_uninstall_binary_test_', true);
        mkdir($fakeExtensionPath, recursive: true);
        $extensionFile = $fakeExtensionPath . DIRECTORY_SEPARATOR . 'foobar.so';
        file_put_contents($extensionFile, 'test content');
        $testHash = hash_file('sha256', $extensionFile);

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('extensionPath')
            ->willReturn($fakeExtensionPath);

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
        );

        $composerPackage = $this->createMock(CompletePackageInterface::class);
        $composerPackage
            ->method('getExtra')
            ->willReturn([
                PieInstalledJsonMetadataKeys::InstalledBinary->value => $extensionFile,
                PieInstalledJsonMetadataKeys::BinaryChecksum->value => $testHash,
            ]);

        $package = new Package(
            $composerPackage,
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foobar'),
            'foobar/foobar',
            '1.2.3',
            null,
        );

        $uninstalled = (new UninstallUsingUnlink())($targetPlatform, $package);

        self::assertSame($extensionFile, $uninstalled->filePath);
        self::assertFileDoesNotExist($extensionFile);
        (new Filesystem())->remove($fakeExtensionPath);
    }

    public function testExtensionPathInMetadataNotMatchingConventionWillThrowException(): void
    {
        $fakeExtensionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_uninstall_binary_test_', true);
        mkdir($fakeExtensionPath, recursive: true);
        $extensionFile = $fakeExtensionPath . DIRECTORY_SEPARATOR . 'foobar.so';
        file_put_contents($extensionFile, 'test content');
        $testHash = hash_file('sha256', $extensionFile);

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('extensionPath')
            ->willReturn('/different/expected/ext/path');

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
        );

        $composerPackage = $this->createMock(CompletePackageInterface::class);
        $composerPackage
            ->method('getExtra')
            ->willReturn([
                PieInstalledJsonMetadataKeys::InstalledBinary->value => $extensionFile,
                PieInstalledJsonMetadataKeys::BinaryChecksum->value => $testHash,
            ]);

        $package = new Package(
            $composerPackage,
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foobar'),
            'foobar/foobar',
            '1.2.3',
            null,
        );

        try {
            (new UninstallUsingUnlink())($targetPlatform, $package);
            self::fail('Expected exception was NOT thrown');
        } catch (RuntimeException $e) {
            self::assertSame(
                'Stored metadata path "' . $extensionFile . '" did not match expected path "/different/expected/ext/path/foobar.so"',
                $e->getMessage(),
            );
        }

        self::assertFileExists($extensionFile);
        (new Filesystem())->remove($fakeExtensionPath);
    }
}
