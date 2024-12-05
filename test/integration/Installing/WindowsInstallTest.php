<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Installing;

use Composer\Package\CompletePackage;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\Ini\PickBestSetupIniApproach;
use Php\Pie\Installing\SetupIniFile;
use Php\Pie\Installing\WindowsInstall;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use Php\Pie\Platform\WindowsCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Output\BufferedOutput;

use function assert;
use function dirname;
use function file_exists;
use function is_dir;
use function rmdir;
use function str_replace;
use function unlink;

use const DIRECTORY_SEPARATOR;

#[CoversClass(WindowsInstall::class)]
final class WindowsInstallTest extends TestCase
{
    private const TEST_EXTENSION_PATH = __DIR__ . '/../../assets/pie_test_ext_win';

    #[RequiresOperatingSystemFamily('Windows')]
    public function testWindowsInstallCanInstallExtension(): void
    {
        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            new Package(
                $this->createMock(CompletePackage::class),
                ExtensionType::PhpModule,
                ExtensionName::normaliseFromString('pie_test_ext'),
                'php/pie-test-ext',
                '1.2.3',
                null,
                [],
                true,
                true,
                null,
                null,
                null,
            ),
            self::TEST_EXTENSION_PATH,
        );
        $output            = new BufferedOutput();
        $targetPlatform    = new TargetPlatform(
            OperatingSystem::Windows,
            OperatingSystemFamily::Windows,
            PhpBinaryPath::fromCurrentProcess(),
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            WindowsCompiler::VS16,
        );
        $phpPath           = dirname($targetPlatform->phpBinaryPath->phpBinaryPath);
        $extensionPath     = $targetPlatform->phpBinaryPath->extensionPath();

        $installer = new WindowsInstall(new SetupIniFile(new PickBestSetupIniApproach([])));

        $installedDll = $installer->__invoke($downloadedPackage, $targetPlatform, $output);
        self::assertSame($extensionPath . '\php_pie_test_ext.dll', $installedDll->filePath);

        $outputString = $output->fetch();

        self::assertStringContainsString('Copied DLL to: ' . $extensionPath . '\php_pie_test_ext.dll', $outputString);
        self::assertStringContainsString('You must now add "extension=pie_test_ext" to your php.ini', $outputString);

        $extrasDirectory = $phpPath . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'pie_test_ext';

        $expectedPdb                 = str_replace('.dll', '.pdb', $installedDll->filePath);
        $expectedSupportingDll       = $phpPath . DIRECTORY_SEPARATOR . 'supporting-library.dll';
        $expectedSupportingOtherFile = $extrasDirectory . DIRECTORY_SEPARATOR . 'README.md';
        $expectedSubdirectoryFile    = $extrasDirectory . DIRECTORY_SEPARATOR . 'more' . DIRECTORY_SEPARATOR . 'more-information.txt';
        assert($expectedPdb !== '');

        self::assertFileExists($installedDll->filePath);
        self::assertFileExists($expectedPdb);
        self::assertFileExists($expectedSupportingDll);
        self::assertFileExists($expectedSupportingOtherFile);
        self::assertFileExists($expectedSubdirectoryFile);

        $this->delete($installedDll->filePath);
        $this->delete($expectedPdb);
        $this->delete($expectedSupportingDll);
        $this->delete($extrasDirectory);
    }

    /**
     * Recursively remove a file/path to clean up after testing
     *
     * @param non-empty-string $path
     */
    private function delete(string $path): void
    {
        if (! file_exists($path)) {
            return;
        }

        if (! is_dir($path)) {
            unlink($path);

            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $fileinfo) {
            assert($fileinfo instanceof SplFileInfo);
            if ($fileinfo->isDir()) {
                rmdir($fileinfo->getRealPath());
                continue;
            }

            unlink($fileinfo->getRealPath());
        }

        rmdir($path);
    }
}
