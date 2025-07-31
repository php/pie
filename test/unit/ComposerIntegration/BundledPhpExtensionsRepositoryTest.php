<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration;

use Composer\Package\CompletePackageInterface;
use Php\Pie\ComposerIntegration\BundledPhpExtensionsRepository;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use Php\Pie\Util\Process;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

use function mkdir;
use function realpath;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

#[CoversClass(BundledPhpExtensionsRepository::class)]
final class BundledPhpExtensionsRepositoryTest extends TestCase
{
    /** @return list<array{0: non-empty-string}> */
    public static function bundledRepositoryPackageNames(): array
    {
        return [
            ['php/bcmath'],
            ['php/bz2'],
            ['php/calendar'],
            ['php/ctype'],
            ['php/curl'],
            ['php/dba'],
            ['php/dom'],
            ['php/enchant'],
            ['php/exif'],
            ['php/ffi'],
            ['php/gettext'],
            ['php/gmp'],
            ['php/iconv'],
            ['php/intl'],
            ['php/ldap'],
            ['php/mbstring'],
            ['php/mysqlnd'],
            ['php/mysqli'],
            ['php/opcache'],
            ['php/pcntl'],
            ['php/pdo'],
            ['php/pdo_mysql'],
            ['php/pdo_pgsql'],
            ['php/pdo_sqlite'],
            ['php/pgsql'],
            ['php/posix'],
            ['php/readline'],
            ['php/session'],
            ['php/shmop'],
            ['php/simplexml'],
            ['php/snmp'],
            ['php/soap'],
            ['php/sockets'],
            ['php/sodium'],
            ['php/sqlite3'],
            ['php/sysvmsg'],
            ['php/sysvsem'],
            ['php/sysvshm'],
            ['php/tidy'],
            ['php/xml'],
            ['php/xmlreader'],
            ['php/xmlwriter'],
            ['php/xsl'],
            ['php/zip'],
            ['php/zlib'],
        ];
    }

    #[DataProvider('bundledRepositoryPackageNames')]
    public function testBundledRepository(string $packageName): void
    {
        $phpBinary = $this->createMock(PhpBinaryPath::class);
        $phpBinary->expects(self::once())
            ->method('version')
            ->willReturn('8.1.0');

        $repository = BundledPhpExtensionsRepository::forTargetPlatform(
            new TargetPlatform(
                OperatingSystem::NonWindows,
                OperatingSystemFamily::Linux,
                $phpBinary,
                Architecture::x86_64,
                ThreadSafetyMode::NonThreadSafe,
                1,
                null,
            ),
        );

        $package = $repository->findPackage($packageName, '8.1.0');
        self::assertNotNull($package);
        self::assertSame($packageName, $package->getName());
        self::assertSame('8.1.0', $package->getPrettyVersion());
    }

    public function testMakeCommandForXmlReader(): void
    {
        $phpPath       = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_test_bundled_', true);
        $xmlReaderPath = $phpPath . DIRECTORY_SEPARATOR . 'ext' . DIRECTORY_SEPARATOR . 'xmlreader';
        mkdir($xmlReaderPath, recursive: true);

        self::assertEquals(
            ['EXTRA_CFLAGS=-I' . realpath($phpPath)],
            BundledPhpExtensionsRepository::augmentMakeCommandForPhpBundledExtensions(
                [],
                DownloadedPackage::fromPackageAndExtractedPath(
                    new Package(
                        $this->createMock(CompletePackageInterface::class),
                        ExtensionType::PhpModule,
                        ExtensionName::normaliseFromString('xmlreader'),
                        'php/xmlreader',
                        '1.2.3',
                        null,
                    ),
                    realpath($xmlReaderPath),
                ),
            ),
        );
    }

    public function testMakeCommandForDom(): void
    {
        $phpPath    = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_test_bundled_', true);
        $domPath    = $phpPath . DIRECTORY_SEPARATOR . 'ext' . DIRECTORY_SEPARATOR . 'dom';
        $lexborPath = $phpPath . DIRECTORY_SEPARATOR . 'ext' . DIRECTORY_SEPARATOR . 'lexbor';
        mkdir($domPath, recursive: true);
        mkdir($lexborPath, recursive: true);

        self::assertEquals(
            ['EXTRA_CFLAGS=-I' . realpath($phpPath) . ' -I' . realpath($lexborPath)],
            BundledPhpExtensionsRepository::augmentMakeCommandForPhpBundledExtensions(
                [],
                DownloadedPackage::fromPackageAndExtractedPath(
                    new Package(
                        $this->createMock(CompletePackageInterface::class),
                        ExtensionType::PhpModule,
                        ExtensionName::normaliseFromString('dom'),
                        'php/dom',
                        '1.2.3',
                        null,
                    ),
                    realpath($domPath),
                ),
            ),
        );
    }

    /** @return list<array{0: non-empty-string}> */
    public static function dependantsOnRe2c(): array
    {
        return [
            ['pdo'],
            ['pdo_mysql'],
            ['pdo_pgsql'],
            ['pdo_sqlite'],
        ];
    }

    #[DataProvider('dependantsOnRe2c')]
    public function testMakeCommandForRe2cDependants(string $extensionName): void
    {
        try {
            $re2cPath = Process::run(['which', 're2c']);
        } catch (ProcessFailedException) {
            self::markTestSkipped('re2c not installed');
        }

        self::assertEquals(
            ['RE2C=' . $re2cPath],
            BundledPhpExtensionsRepository::augmentMakeCommandForPhpBundledExtensions(
                [],
                DownloadedPackage::fromPackageAndExtractedPath(
                    new Package(
                        $this->createMock(CompletePackageInterface::class),
                        ExtensionType::PhpModule,
                        ExtensionName::normaliseFromString($extensionName),
                        'php/' . $extensionName,
                        '1.2.3',
                        null,
                    ),
                    '/path/to/ext',
                ),
            ),
        );
    }
}
