<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Building;

use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Util\Filesystem;
use Php\Pie\Building\PlaceholderReplacer;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Safe\DateTimeImmutable;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\mkdir;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

#[CoversClass(PlaceholderReplacer::class)]
final class PlaceholderReplacerTest extends TestCase
{
    private const ORIGINAL_CONTENT = <<<'EOF'
name: @name@ @package_name@ @package-name@
version: @package_version@ @package-version@
date: @release_date@ @release-date@
php-bin: @php_bin@ @php-bin@
EOF;
    private const EXPECTED_CONTENT = <<<'EOF'
name: myext myext myext
version: 1.2.3 1.2.3
date: 2005-12-31T14:55:56+00:00 2005-12-31T14:55:56+00:00
php-bin: /path/to/php /path/to/php
EOF;

    public function testReplacePlaceholdersWithPlaceholderReplacements(): void
    {
        $testPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_test_placeholders_', true);
        mkdir($testPath, recursive: true);

        file_put_contents($testPath . DIRECTORY_SEPARATOR . 'hello.rs', self::ORIGINAL_CONTENT);
        file_put_contents($testPath . DIRECTORY_SEPARATOR . 'hello.c', self::ORIGINAL_CONTENT);
        file_put_contents($testPath . DIRECTORY_SEPARATOR . 'hello.h', self::ORIGINAL_CONTENT);

        $releaseDate = new DateTimeImmutable('2005-12-31 14:55:56');

        $composerPackage = new CompletePackage('myext/myext', '1.2.3.0', '1.2.3');
        $composerPackage->setReleaseDate($releaseDate);

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            Package::fromComposerCompletePackage($composerPackage),
            $testPath,
        );

        $mockPhpBinary = $this->createMock(PhpBinaryPath::class);
        /** @phpstan-ignore property.notFound */
        (fn () => $this->phpBinaryPath = '/path/to/php')
            ->bindTo($mockPhpBinary, PhpBinaryPath::class)();

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $mockPhpBinary,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
            null,
        );

        $replacer = new PlaceholderReplacer();
        $replacer->replacePlaceholdersWithPlaceholderReplacements(
            $this->createMock(IOInterface::class),
            $targetPlatform,
            $downloadedPackage,
        );

        self::assertSame(self::ORIGINAL_CONTENT, file_get_contents($testPath . DIRECTORY_SEPARATOR . 'hello.rs'));
        self::assertSame(self::EXPECTED_CONTENT, file_get_contents($testPath . DIRECTORY_SEPARATOR . 'hello.c'));
        self::assertSame(self::EXPECTED_CONTENT, file_get_contents($testPath . DIRECTORY_SEPARATOR . 'hello.h'));

        (new Filesystem())->remove($testPath);
    }
}
