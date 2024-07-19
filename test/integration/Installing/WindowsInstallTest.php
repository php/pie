<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Installing;

use Composer\Util\Platform;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\WindowsInstall;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use Php\Pie\Platform\WindowsCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

use function dirname;
use function str_replace;

use const DIRECTORY_SEPARATOR;

#[CoversClass(WindowsInstall::class)]
final class WindowsInstallTest extends TestCase
{
    private const TEST_EXTENSION_PATH = __DIR__ . '/../../assets/pie_test_ext_win';

    public function testWindowsInstallCanInstallExtension(): void
    {
        if (! Platform::isWindows()) {
            self::markTestSkipped('Test can only be run on Windows');
        }

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            new Package(
                ExtensionType::PhpModule,
                ExtensionName::normaliseFromString('pie_test_ext'),
                'php/pie-test-ext',
                '1.2.3',
                null,
                [],
            ),
            self::TEST_EXTENSION_PATH,
        );
        $output            = new BufferedOutput();
        $targetPlatform    = new TargetPlatform(
            OperatingSystem::Windows,
            PhpBinaryPath::fromCurrentProcess(),
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            WindowsCompiler::VS16,
        );
        $phpPath           = dirname($targetPlatform->phpBinaryPath->phpBinaryPath);
        $extensionPath     = $targetPlatform->phpBinaryPath->extensionPath();

        $installer = new WindowsInstall();

        $installedDll = $installer->__invoke($downloadedPackage, $targetPlatform, $output);
        self::assertSame($extensionPath . '\php_pie_test_ext.dll', $installedDll);

        $outputString = $output->fetch();

        self::assertStringContainsString('Copied DLL to: ' . $extensionPath . '\php_pie_test_ext.dll', $outputString);
        self::assertStringContainsString('You must now add "extension=pie_test_ext" to your php.ini', $outputString);

        $extrasDirectory = $phpPath . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'pie_test_ext';

        $expectedPdb                 = str_replace('.dll', '.pdb', $installedDll);
        $expectedSupportingDll       = $phpPath . DIRECTORY_SEPARATOR . 'supporting-library.dll';
        $expectedSupportingOtherFile = $extrasDirectory . DIRECTORY_SEPARATOR . 'README.md';
        $expectedSubdirectoryFile    = $extrasDirectory . DIRECTORY_SEPARATOR . 'more' . DIRECTORY_SEPARATOR . 'more-information.txt';

        self::assertFileExists($installedDll);
        self::assertFileExists($expectedPdb);
        self::assertFileExists($expectedSupportingDll);
        self::assertFileExists($expectedSupportingOtherFile);
        self::assertFileExists($expectedSubdirectoryFile);

        (new Process(['del', $installedDll]))->mustRun();
        (new Process(['del', $expectedPdb]))->mustRun();
        (new Process(['del', $expectedSupportingDll]))->mustRun();
        (new Process(['del', $extrasDirectory]))->mustRun();
    }
}
