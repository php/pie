<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing;

use Composer\Package\CompletePackageInterface;
use Php\Pie\ComposerIntegration\PieInstalledJsonMetadataKeys;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\PackageMetadataMissing;
use Php\Pie\Installing\UninstallUsingUnlink;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function hash_file;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

#[CoversClass(UninstallUsingUnlink::class)]
final class UninstallUsingUnlinkTest extends TestCase
{
    public function testMissingMetadataThrowsException(): void
    {
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
        (new UninstallUsingUnlink())($package);
    }

    public function testBinaryFileIsRemoved(): void
    {
        $testFilename = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_uninstall_binary_test_', true);
        file_put_contents($testFilename, 'test content');
        $testHash = hash_file('sha256', $testFilename);

        $composerPackage = $this->createMock(CompletePackageInterface::class);
        $composerPackage
            ->method('getExtra')
            ->willReturn([
                PieInstalledJsonMetadataKeys::InstalledBinary->value => $testFilename,
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

        $uninstalled = (new UninstallUsingUnlink())($package);

        self::assertSame($testFilename, $uninstalled->filePath);
        self::assertFileDoesNotExist($testFilename);
    }
}
