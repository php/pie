<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration;

use Composer\Package\CompletePackageInterface;
use Php\Pie\ComposerIntegration\PieInstalledJsonMetadataKeys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PieInstalledJsonMetadataKeys::class)]
final class PieInstalledJsonMetadataKeysTest extends TestCase
{
    public function testPieMetadataFromComposerPackageWithEmptyExtra(): void
    {
        $composerPackage = $this->createMock(CompletePackageInterface::class);
        $composerPackage->expects(self::once())
            ->method('getExtra')
            ->willReturn([]);

        self::assertSame([], PieInstalledJsonMetadataKeys::pieMetadataFromComposerPackage($composerPackage));
    }

    public function testPieMetadataFromComposerPackageWithPopulatedExtra(): void
    {
        $composerPackage = $this->createMock(CompletePackageInterface::class);
        $composerPackage->expects(self::once())
            ->method('getExtra')
            ->willReturn([
                PieInstalledJsonMetadataKeys::InstalledBinary->value => '/path/to/some/file',
                PieInstalledJsonMetadataKeys::BinaryChecksum->value => 'some-checksum-value',
                'something else' => 'hopefully this does not make it in',
            ]);

        self::assertEqualsCanonicalizing(
            [
                PieInstalledJsonMetadataKeys::InstalledBinary->value => '/path/to/some/file',
                PieInstalledJsonMetadataKeys::BinaryChecksum->value => 'some-checksum-value',
            ],
            PieInstalledJsonMetadataKeys::pieMetadataFromComposerPackage($composerPackage),
        );
    }
}
