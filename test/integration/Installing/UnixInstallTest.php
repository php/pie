<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Installing;

use Composer\Util\Platform;
use Php\Pie\Building\UnixBuild;
use Php\Pie\ConfigureOption;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\UnixInstall;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Process\Process;

/** @covers \Php\Pie\Installing\UnixInstall */
#[CoversClass(UnixInstall::class)]
final class UnixInstallTest extends TestCase
{
    private const TEST_EXTENSION_PATH = __DIR__ . '/../../assets/pie_test_ext';

    public function testUnixInstallCanInstallExtension(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Unix build test cannot be run on Windows');
        }

        $output         = new BufferedOutput();
        $targetPlatform = TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess());
        $extensionPath  = $targetPlatform->phpBinaryPath->extensionPath();

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            new Package(
                ExtensionType::PhpModule,
                ExtensionName::normaliseFromString('pie_test_ext'),
                'pie_test_ext',
                '0.1.0',
                null,
                [ConfigureOption::fromComposerJsonDefinition(['name' => 'enable-pie_test_ext'])],
            ),
            self::TEST_EXTENSION_PATH,
        );

        (new UnixBuild())->__invoke(
            $downloadedPackage,
            $targetPlatform,
            ['--enable-pie_test_ext'],
            new NullOutput(),
        );

        $installedSharedObject = (new UnixInstall())->__invoke(
            $downloadedPackage,
            $targetPlatform,
            $output,
        );

        $outputString = $output->fetch();

        self::assertStringContainsString('Install complete: ' . $extensionPath . '/pie_test_ext.so', $outputString);
        self::assertStringContainsString('You must now add "extension=pie_test_ext.so" to your php.ini', $outputString);

        self::assertSame($extensionPath . '/pie_test_ext.so', $installedSharedObject);
        self::assertFileExists($installedSharedObject);

        (new Process(['sudo', 'rm', $installedSharedObject]))->mustRun();
        (new Process(['make', 'clean'], $downloadedPackage->extractedSourcePath))->mustRun();
        (new Process(['phpize', '--clean'], $downloadedPackage->extractedSourcePath))->mustRun();
    }
}
