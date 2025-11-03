<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration;

use Composer\Composer;
use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
use Composer\Semver\Constraint\Constraint;
use Composer\Util\Platform;
use Php\Pie\ComposerIntegration\PhpBinaryPathBasedPlatformRepository;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionName;
use Php\Pie\Platform\InstalledPiePackages;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Util\Process;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

use function array_combine;
use function array_filter;
use function array_map;
use function in_array;
use function str_starts_with;

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
                array_filter(
                    $platformRepository->getPackages(),
                    static fn (PackageInterface $package): bool => ! str_starts_with($package->getName(), 'lib-'),
                ),
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
                array_filter(
                    $platformRepository->getPackages(),
                    static fn (PackageInterface $package): bool => ! str_starts_with($package->getName(), 'lib-'),
                ),
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
                array_filter(
                    $platformRepository->getPackages(),
                    static fn (PackageInterface $package): bool => ! str_starts_with($package->getName(), 'lib-'),
                ),
            ),
        );
    }

    /** @return array<non-empty-string, array{0: non-empty-string}> */
    public static function installedLibraries(): array
    {
        // data providers cannot return empty, even if the test is skipped
        if (Platform::isWindows()) {
            return ['skip' => ['skip']];
        }

        $installedLibs = array_filter(
            [
                ['curl', 'libcurl'],
                ['enchant', 'enchant'],
                ['enchant-2', 'enchant-2'],
                ['sodium', 'libsodium'],
                ['ffi', 'libffi'],
                ['xslt', 'libxslt'],
                ['zip', 'libzip'],
                ['png', 'libpng'],
                ['avif', 'libavif'],
                ['webp', 'libwebp'],
                ['jpeg', 'libjpeg'],
                ['xpm', 'xpm'],
                ['freetype2', 'freetype2'],
                ['gdlib', 'gdlib'],
                ['gmp', 'gmp'],
                ['sasl', 'libsasl2'],
                ['onig', 'oniguruma'],
                ['odbc', 'libiodbc'],
                ['capstone', 'capstone'],
                ['pcre', 'libpcre2-8'],
                ['edit', 'libedit'],
                ['snmp', 'netsnmp'],
                ['argon2', 'libargon2'],
                ['uriparser', 'liburiparser'],
                ['exslt', 'libexslt'],
            ],
            static function (array $pkg): bool {
                try {
                    Process::run(['pkg-config', '--print-provides', '--print-errors', $pkg[1]], timeout: 30);

                    return true;
                } catch (ProcessFailedException) {
                    return false;
                }
            },
        );

        return array_combine(
            array_map(
                static fn (array $pkg): string => $pkg[0],
                $installedLibs,
            ),
            array_map(
                static fn (array $pkg): array => [$pkg[0]],
                $installedLibs,
            ),
        );
    }

    #[DataProvider('installedLibraries')]
    public function testLibrariesAreIncluded(string $packageName): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('pkg-config not available on Windows');
        }

        self::assertTrue(in_array(
            'lib-' . $packageName,
            array_map(
                static fn (PackageInterface $package): string => $package->getName(),
                (new PhpBinaryPathBasedPlatformRepository(
                    PhpBinaryPath::fromCurrentProcess(),
                    $this->createMock(Composer::class),
                    $this->createMock(InstalledPiePackages::class),
                    ExtensionName::normaliseFromString('extension_being_installed'),
                ))->getPackages(),
            ),
        ));
    }
}
