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
use Php\Pie\Platform\PiePackageList;
use Php\Pie\Platform\PkgConfig;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Util\Process;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

use function array_combine;
use function array_filter;
use function array_map;
use function array_values;
use function in_array;
use function str_starts_with;

#[CoversClass(PhpBinaryPathBasedPlatformRepository::class)]
final class PhpBinaryPathBasedPlatformRepositoryTest extends TestCase
{
    public function testPlatformRepositoryContainsExpectedPacakges(): void
    {
        $composer = $this->createMock(Composer::class);

        $installedPiePackages = $this->createMock(InstalledPiePackages::class);
        $installedPiePackages->method('allPiePackages')->willReturn(new PiePackageList([]));

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

        $platformRepository = new PhpBinaryPathBasedPlatformRepository($phpBinaryPath, $composer, $installedPiePackages, $this->createMock(PkgConfig::class), []);

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
        $installedPiePackages->method('allPiePackages')->willReturn(new PiePackageList([]));

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

        $platformRepository = new PhpBinaryPathBasedPlatformRepository($phpBinaryPath, $composer, $installedPiePackages, $this->createMock(PkgConfig::class), [$extensionBeingInstalled]);

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
        $installedPiePackages->method('allPiePackages')->willReturn(new PiePackageList([
            Package::fromComposerCompletePackage($composerPackage),
        ]));

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

        $platformRepository = new PhpBinaryPathBasedPlatformRepository($phpBinaryPath, $composer, $installedPiePackages, $this->createMock(PkgConfig::class), [$extensionBeingInstalled]);

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

    public function testPlatformRepositoryContainsLibrariesReportedByPkgConfig(): void
    {
        $installedPiePackages = $this->createMock(InstalledPiePackages::class);
        $installedPiePackages->method('allPiePackages')->willReturn(new PiePackageList([]));

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->method('version')->willReturn('8.1.0');
        $phpBinaryPath->method('extensions')->willReturn(['foo' => '8.1.0']);

        $pkgConfig = $this->createMock(PkgConfig::class);
        $pkgConfig->expects(self::once())
            ->method('versionsOf')
            ->with(self::containsIdentical('libpcre2-8'))
            ->willReturn([
                'libpcre2-8' => '10.45',
                'libcurl' => '8.12.1',
                'gdlib' => 'not-a-version',
            ]);

        $platformRepository = new PhpBinaryPathBasedPlatformRepository($phpBinaryPath, $this->createMock(Composer::class), $installedPiePackages, $pkgConfig, []);

        self::assertSame(
            [
                'lib-curl:8.12.1:8.12.1.0',
                'lib-gdlib:not-a-version:*',
                'lib-pcre:10.45:10.45.0.0',
            ],
            array_values(array_map(
                static fn (PackageInterface $package): string => $package->getName() . ':' . $package->getPrettyVersion() . ':' . $package->getVersion(),
                array_filter(
                    $platformRepository->getPackages(),
                    static fn (PackageInterface $package): bool => str_starts_with($package->getName(), 'lib-'),
                ),
            )),
        );
    }

    /** @return array<non-empty-string, array{0: non-empty-string, 1: non-empty-string, 2: non-empty-string}> */
    public static function librariesDetectedUsingPkgConfig(): array
    {
        return [
            'curl' => ['curl', 'libcurl', '8.12.1'],
            'enchant' => ['enchant', 'enchant', '1.6.0'],
            'enchant-2' => ['enchant-2', 'enchant-2', '2.3.3'],
            'sodium' => ['sodium', 'libsodium', '1.0.18'],
            'ffi' => ['ffi', 'libffi', '3.4.6'],
            'xslt' => ['xslt', 'libxslt', '1.1.39'],
            'zip' => ['zip', 'libzip', '1.10.1'],
            'png' => ['png', 'libpng', '1.6.43'],
            'avif' => ['avif', 'libavif', '1.0.4'],
            'webp' => ['webp', 'libwebp', '1.3.2'],
            'jpeg' => ['jpeg', 'libjpeg', '2.1.5'],
            'xpm' => ['xpm', 'xpm', '3.5.17'],
            'freetype2' => ['freetype2', 'freetype2', '26.1.20'],
            'gdlib' => ['gdlib', 'gdlib', '2.3.3'],
            'gmp' => ['gmp', 'gmp', '6.3.0'],
            'gpgme' => ['gpgme', 'gpgme', '1.18.0'],
            'pam' => ['pam', 'pam', '1.5.3'],
            'sasl' => ['sasl', 'libsasl2', '2.1.28'],
            'onig' => ['onig', 'oniguruma', '6.9.9'],
            'odbc' => ['odbc', 'libiodbc', '3.52.16'],
            'capstone' => ['capstone', 'capstone', '4.0.2'],
            'pcre' => ['pcre', 'libpcre2-8', '10.42'],
            'edit' => ['edit', 'libedit', '3.1'],
            'snmp' => ['snmp', 'netsnmp', '5.9.4'],
            'argon2' => ['argon2', 'libargon2', '20190702'],
            'uriparser' => ['uriparser', 'liburiparser', '0.9.7'],
            'exslt' => ['exslt', 'libexslt', '0.8.21'],
            'yaml' => ['yaml', 'yaml-0.1', '0.2.5'],
            'uv' => ['uv', 'libuv', '1.48.0'],
            'uuid' => ['uuid', 'uuid', '2.39.3'],
            'rdkafka' => ['rdkafka', 'rdkafka', '2.3.0'],
            'zlib' => ['zlib', 'zlib', '1.3'],
            'memcached' => ['memcached', 'libmemcached', '1.1.4'],
            'imagemagick' => ['imagemagick', 'MagickWand', '6.9.12'],
        ];
    }

    #[DataProvider('librariesDetectedUsingPkgConfig')]
    public function testLibraryIsDetectedUsingPkgConfig(string $alias, string $pkgConfigName, string $version): void
    {
        $installedPiePackages = $this->createMock(InstalledPiePackages::class);
        $installedPiePackages->method('allPiePackages')->willReturn(new PiePackageList([]));

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->method('version')->willReturn('8.1.0');
        $phpBinaryPath->method('extensions')->willReturn([]);

        $pkgConfig = $this->createMock(PkgConfig::class);
        $pkgConfig->method('versionsOf')->willReturn([$pkgConfigName => $version]);

        $platformRepository = new PhpBinaryPathBasedPlatformRepository($phpBinaryPath, $this->createMock(Composer::class), $installedPiePackages, $pkgConfig, []);

        $lib = $platformRepository->findPackage('lib-' . $alias, '*');
        self::assertNotNull($lib);
        self::assertSame($version, $lib->getPrettyVersion());
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
                ['gpgme', 'gpgme'],
                ['pam', 'pam'],
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
                ['yaml', 'yaml-0.1'],
                ['uv', 'libuv'],
                ['uuid', 'uuid'],
                ['rdkafka', 'rdkafka'],
                ['zlib', 'zlib'],
                ['memcached', 'libmemcached'],
                ['imagemagick', 'MagickWand'],
            ],
            static function (array $pkg): bool {
                try {
                    Process::run(['pkg-config', '--print-provides', '--print-errors', $pkg[1]]);

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

        $installedPiePackages = $this->createMock(InstalledPiePackages::class);
        $installedPiePackages->method('allPiePackages')->willReturn(new PiePackageList([]));

        self::assertTrue(in_array(
            'lib-' . $packageName,
            array_map(
                static fn (PackageInterface $package): string => $package->getName(),
                (new PhpBinaryPathBasedPlatformRepository(
                    PhpBinaryPath::fromCurrentProcess(),
                    $this->createMock(Composer::class),
                    $installedPiePackages,
                    PkgConfig::detect(),
                    [ExtensionName::normaliseFromString('extension_being_installed')],
                ))->getPackages(),
            ),
        ));
    }
}
