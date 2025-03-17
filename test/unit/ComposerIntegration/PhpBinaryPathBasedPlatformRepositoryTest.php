<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration;

use Composer\Composer;
use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
use Composer\Semver\Constraint\Constraint;
use Php\Pie\ComposerIntegration\PhpBinaryPathBasedPlatformRepository;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionName;
use Php\Pie\Platform\InstalledPiePackages;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(PhpBinaryPathBasedPlatformRepository::class)]
final class PhpBinaryPathBasedPlatformRepositoryTest extends TestCase
{
    public function testPlatformRepositoryContainsExpectedPacakges(): void
    {
        $composer = $this->createMock(Composer::class);

        $installedPiePackages = $this->createMock(InstalledPiePackages::class);
        $installedPiePackages->method('allPiePackages')->willReturn([]);

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::once())
            ->method('version')
            ->willReturn('8.1.0');
        $phpBinaryPath->expects(self::once())
            ->method('extensions')
            ->willReturn([
                'json' => '8.1.0-extra',
                'foo' => '8.1.0',
                'without-version' => '0',
                'another' => '1.2.3-alpha.34',
            ]);

        $platformRepository = new PhpBinaryPathBasedPlatformRepository($phpBinaryPath, $composer, $installedPiePackages, null);

        self::assertSame(
            [
                'php:8.1.0',
                'ext-json:8.1.0',
                'ext-foo:8.1.0',
                'ext-without-version:0',
                'ext-another:1.2.3-alpha.34',
            ],
            array_map(
                static fn (PackageInterface $package): string => $package->getName() . ':' . $package->getPrettyVersion(),
                $platformRepository->getPackages(),
            ),
        );
    }

    public function testPlatformRepositoryExcludesExtensionBeingInstalled(): void
    {
        $composer = $this->createMock(Composer::class);

        $installedPiePackages = $this->createMock(InstalledPiePackages::class);
        $installedPiePackages->method('allPiePackages')->willReturn([]);

        $extensionBeingInstalled = ExtensionName::normaliseFromString('extension_being_installed');

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::once())
            ->method('version')
            ->willReturn('8.1.0');
        $phpBinaryPath->expects(self::once())
            ->method('extensions')
            ->willReturn([
                'foo' => '8.1.0',
                'extension_being_installed' => '1.2.3',
            ]);

        $platformRepository = new PhpBinaryPathBasedPlatformRepository($phpBinaryPath, $composer, $installedPiePackages, $extensionBeingInstalled);

        self::assertSame(
            [
                'php:8.1.0',
                'ext-foo:8.1.0',
            ],
            array_map(
                static fn (PackageInterface $package): string => $package->getName() . ':' . $package->getPrettyVersion(),
                $platformRepository->getPackages(),
            ),
        );
    }

    public function testPlatformRepositoryExcludesReplacedExtensions(): void
    {
        $composer = $this->createMock(Composer::class);

        $composerPackage = new CompletePackage('myvendor/replaced_extension', '1.2.3.0', '1.2.3');
        $composerPackage->setReplaces([
            'ext-replaced_extension' => new Link('myvendor/replaced_extension', 'ext-replaced_extension', new Constraint('==', '*')),
        ]);
        $installedPiePackages = $this->createMock(InstalledPiePackages::class);
        $installedPiePackages->method('allPiePackages')->willReturn([
            Package::fromComposerCompletePackage($composerPackage),
        ]);

        $extensionBeingInstalled = ExtensionName::normaliseFromString('extension_being_installed');

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::once())
            ->method('version')
            ->willReturn('8.1.0');
        $phpBinaryPath->expects(self::once())
            ->method('extensions')
            ->willReturn([
                'foo' => '8.1.0',
                'replaced_extension' => '3.0.0',
            ]);

        $platformRepository = new PhpBinaryPathBasedPlatformRepository($phpBinaryPath, $composer, $installedPiePackages, $extensionBeingInstalled);

        self::assertSame(
            [
                'php:8.1.0',
                'ext-foo:8.1.0',
            ],
            array_map(
                static fn (PackageInterface $package): string => $package->getName() . ':' . $package->getPrettyVersion(),
                $platformRepository->getPackages(),
            ),
        );
    }
}
