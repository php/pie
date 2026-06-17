<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\InstallForPhpProject;

use Composer\Composer;
use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use OutOfRangeException;
use Php\Pie\ExtensionName;
use Php\Pie\Installing\InstallForPhpProject\FindMatchingPackages;
use Php\Pie\Installing\InstallForPhpProject\NoMatchingPackagesFound;
use Php\Pie\Installing\InstallForPhpProject\PackageSelectionRequired;
use Php\Pie\Installing\InstallForPhpProject\SelectPackageForExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SelectPackageForExtension::class)]
final class SelectPackageForExtensionTest extends TestCase
{
    private FindMatchingPackages&MockObject $findMatchingPackages;
    private BufferIO $io;
    private SelectPackageForExtension $selectPackageForExtension;
    private Composer&MockObject $pieComposer;
    private ExtensionName $extension;

    /** @var list<array{name: string, description: ?string}> */
    private array $matches = [
        ['name' => 'vendor/foobar', 'description' => 'The best foobar extension'],
        ['name' => 'other/foobar', 'description' => 'Another foobar option'],
    ];

    public function setUp(): void
    {
        parent::setUp();

        $this->findMatchingPackages      = $this->createMock(FindMatchingPackages::class);
        $this->io                        = new BufferIO();
        $this->selectPackageForExtension = new SelectPackageForExtension($this->findMatchingPackages, $this->io);
        $this->pieComposer               = $this->createMock(Composer::class);
        $this->extension                 = ExtensionName::normaliseFromString('foobar');
    }

    public function testExplicitSelectOptionIsUsedWithoutLookup(): void
    {
        $this->findMatchingPackages->expects(self::never())->method('byProvider');

        $result = ($this->selectPackageForExtension)(
            $this->extension,
            '^1.0',
            ['foobar' => 'vendor/foobar'],
            $this->pieComposer,
            false,
        );

        self::assertNotNull($result);
        self::assertSame('vendor/foobar', $result->package);
        self::assertSame('^1.0', $result->version);
    }

    public function testExplicitSelectWithWildcardConstraintNormalisesToNull(): void
    {
        $this->findMatchingPackages->expects(self::never())->method('byProvider');

        $result = ($this->selectPackageForExtension)(
            $this->extension,
            '*',
            ['foobar' => 'vendor/foobar'],
            $this->pieComposer,
            false,
        );

        self::assertNotNull($result);
        self::assertSame('vendor/foobar', $result->package);
        self::assertNull($result->version);
    }

    public function testNonInteractiveWithNoMatchesThrowsNoMatchingPackagesFound(): void
    {
        $this->findMatchingPackages->method('byProvider')->willThrowException(new OutOfRangeException());

        $this->expectException(NoMatchingPackagesFound::class);

        ($this->selectPackageForExtension)(
            $this->extension,
            '^1.0',
            [],
            $this->pieComposer,
            false,
        );
    }

    public function testNonInteractiveWithMatchesThrowsPackageSelectionRequired(): void
    {
        $this->findMatchingPackages->method('byProvider')->willReturn($this->matches);

        $exception = null;
        try {
            ($this->selectPackageForExtension)(
                $this->extension,
                '^1.0',
                [],
                $this->pieComposer,
                false,
            );
        } catch (PackageSelectionRequired $e) {
            $exception = $e;
        }

        self::assertNotNull($exception);
        self::assertSame('foobar', $exception->extensionName->name());
        self::assertSame($this->matches, $exception->matches);
    }

    public function testInteractiveWithNoMatchesThrowsNoMatchingPackagesFound(): void
    {
        $this->findMatchingPackages->method('byProvider')->willThrowException(new OutOfRangeException());

        $this->expectException(NoMatchingPackagesFound::class);

        ($this->selectPackageForExtension)(
            $this->extension,
            '^1.0',
            [],
            $this->pieComposer,
            true,
        );
    }

    public function testInteractiveUserSelectsPackageWillReturnSelectedPackage(): void
    {
        $this->findMatchingPackages->method('byProvider')->willReturn($this->matches);

        $io = $this->createMock(IOInterface::class);
        $io->method('select')->willReturn('1');

        $service = new SelectPackageForExtension($this->findMatchingPackages, $io);

        $result = $service(
            $this->extension,
            '^1.0',
            [],
            $this->pieComposer,
            true,
        );

        self::assertNotNull($result);
        self::assertSame('vendor/foobar', $result->package);
        self::assertSame('^1.0', $result->version);
    }

    public function testInteractiveUserSelectsNothingReturnsNull(): void
    {
        $this->findMatchingPackages->method('byProvider')->willReturn($this->matches);

        $io = $this->createMock(IOInterface::class);
        $io->method('select')->willReturn('0');
        $io->expects(self::once())->method('write')->with(self::stringContains('won\'t install anything'));

        $service = new SelectPackageForExtension($this->findMatchingPackages, $io);

        $result = $service(
            $this->extension,
            '^1.0',
            [],
            $this->pieComposer,
            true,
        );

        self::assertNull($result);
    }
}
