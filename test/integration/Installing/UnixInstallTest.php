<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Installing;

use Composer\Package\CompletePackage;
use Composer\Util\Platform;
use Php\Pie\Building\UnixBuild;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\Ini\PickBestSetupIniApproach;
use Php\Pie\Installing\SetupIniFile;
use Php\Pie\Installing\UnixInstall;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

use function array_combine;
use function array_filter;
use function array_map;
use function array_unshift;
use function assert;
use function file_exists;
use function is_executable;
use function is_writable;

#[CoversClass(UnixInstall::class)]
final class UnixInstallTest extends TestCase
{
    private const TEST_EXTENSION_PATH = __DIR__ . '/../../assets/pie_test_ext';

    /** @return array<string, array{0: non-empty-string}> */
    public static function phpPathProvider(): array
    {
        // data providers cannot return empty, even if the test is skipped
        if (Platform::isWindows()) {
            return ['skip' => ['skip']];
        }

        $possiblePhpConfigPaths = array_filter(
            [
                '/usr/bin/php-config',
                '/usr/bin/php-config8.4',
                '/usr/bin/php-config8.3',
                '/usr/bin/php-config8.2',
                '/usr/bin/php-config8.1',
                '/usr/bin/php-config8.0',
                '/usr/bin/php-config7.4',
            ],
            static fn (string $phpConfigPath) => file_exists($phpConfigPath)
                && is_executable($phpConfigPath),
        );

        return array_combine(
            $possiblePhpConfigPaths,
            array_map(static fn (string $phpConfigPath) => [$phpConfigPath], $possiblePhpConfigPaths),
        );
    }

    #[DataProvider('phpPathProvider')]
    public function testUnixInstallCanInstallExtension(string $phpConfig): void
    {
        assert($phpConfig !== '');
        if (Platform::isWindows()) {
            self::markTestSkipped('Unix build test cannot be run on Windows');
        }

        $output         = new BufferedOutput();
        $targetPlatform = TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromPhpConfigExecutable($phpConfig), null);
        $extensionPath  = $targetPlatform->phpBinaryPath->extensionPath();

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            new Package(
                $this->createMock(CompletePackage::class),
                ExtensionType::PhpModule,
                ExtensionName::normaliseFromString('pie_test_ext'),
                'pie_test_ext',
                '0.1.0',
                null,
            ),
            self::TEST_EXTENSION_PATH,
        );

        (new UnixBuild())->__invoke(
            $downloadedPackage,
            $targetPlatform,
            ['--enable-pie_test_ext'],
            $output,
            null,
        );

        $installedSharedObject = (new UnixInstall(new SetupIniFile(new PickBestSetupIniApproach([]))))->__invoke(
            $downloadedPackage,
            $targetPlatform,
            $output,
            true,
        );
        $outputString          = $output->fetch();

        self::assertStringContainsString('Install complete: ' . $extensionPath . '/pie_test_ext.so', $outputString);
        self::assertStringContainsString('You must now add "extension=pie_test_ext" to your php.ini', $outputString);

        self::assertSame($extensionPath . '/pie_test_ext.so', $installedSharedObject->filePath);
        self::assertFileExists($installedSharedObject->filePath);

        $rmCommand = ['rm', $installedSharedObject->filePath];
        if (! is_writable($installedSharedObject->filePath)) {
            array_unshift($rmCommand, 'sudo');
        }

        (new Process($rmCommand))->mustRun();
        (new Process(['make', 'clean'], $downloadedPackage->extractedSourcePath))->mustRun();
        (new Process(['phpize', '--clean'], $downloadedPackage->extractedSourcePath))->mustRun();
    }
}
