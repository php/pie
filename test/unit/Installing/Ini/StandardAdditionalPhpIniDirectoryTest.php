<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\Ini;

use Composer\IO\BufferIO;
use Composer\Package\CompletePackageInterface;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\File\BinaryFile;
use Php\Pie\Installing\Ini\CheckAndAddExtensionToIniIfNeeded;
use Php\Pie\Installing\Ini\StandardAdditionalPhpIniDirectory;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function tempnam;
use function touch;
use function unlink;

use const DIRECTORY_SEPARATOR;

#[CoversClass(StandardAdditionalPhpIniDirectory::class)]
final class StandardAdditionalPhpIniDirectoryTest extends TestCase
{
    private BufferIO $io;
    private PhpBinaryPath&MockObject $mockPhpBinary;
    private CheckAndAddExtensionToIniIfNeeded&MockObject $checkAndAddExtensionToIniIfNeeded;
    private TargetPlatform $targetPlatform;
    private DownloadedPackage $downloadedPackage;
    private BinaryFile $binaryFile;
    private StandardAdditionalPhpIniDirectory $standardAdditionalPhpIniDirectory;

    public function setUp(): void
    {
        parent::setUp();

        $this->io = new BufferIO(verbosity: OutputInterface::VERBOSITY_VERBOSE);

        $this->mockPhpBinary = $this->createMock(PhpBinaryPath::class);
        (fn () => $this->phpBinaryPath = '/path/to/php')
            ->bindTo($this->mockPhpBinary, PhpBinaryPath::class)();

        $this->checkAndAddExtensionToIniIfNeeded = $this->createMock(CheckAndAddExtensionToIniIfNeeded::class);

        $this->targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $this->mockPhpBinary,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
        );

        $this->downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            new Package(
                $this->createMock(CompletePackageInterface::class),
                ExtensionType::PhpModule,
                ExtensionName::normaliseFromString('foobar'),
                'foo/bar',
                '1.2.3',
                null,
            ),
            '/path/to/extracted/source',
        );

        $this->binaryFile = new BinaryFile('/path/to/compiled/extension.so', 'fake checksum');

        $this->standardAdditionalPhpIniDirectory = new StandardAdditionalPhpIniDirectory(
            $this->checkAndAddExtensionToIniIfNeeded,
        );
    }

    public function testCannotBeUsedWithNoDefinedAdditionalPhpIniDirectory(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('additionalIniDirectory')
            ->willReturn(null);

        self::assertFalse($this->standardAdditionalPhpIniDirectory->canBeUsed($this->targetPlatform));
    }

    public function testCanBeUsedWithDefinedAdditionalPhpIniDirectory(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('additionalIniDirectory')
            ->willReturn('/path/to/the/php.d');

        self::assertTrue($this->standardAdditionalPhpIniDirectory->canBeUsed($this->targetPlatform));
    }

    public function testSetupReturnsWhenAdditionalPhpIniDirectoryIsNotSet(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('additionalIniDirectory')
            ->willReturn(null);

        $this->checkAndAddExtensionToIniIfNeeded
            ->expects(self::never())
            ->method('__invoke');

        self::assertFalse($this->standardAdditionalPhpIniDirectory->setup(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $this->io,
        ));
    }

    public function testSetupReturnsWhenAdditionalPhpIniDirectoryDoesNotExist(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('additionalIniDirectory')
            ->willReturn('/path/to/something/does/not/exist');

        $this->checkAndAddExtensionToIniIfNeeded
            ->expects(self::never())
            ->method('__invoke');

        self::assertFalse($this->standardAdditionalPhpIniDirectory->setup(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $this->io,
        ));
        self::assertStringContainsString(
            'PHP is configured to use additional INI file path /path/to/something/does/not/exist, but it did not exist',
            $this->io->getOutput(),
        );
    }

    public function testReturnsTrueWhenCheckAndAddExtensionIsInvoked(): void
    {
        $additionalPhpIniDirectory = tempnam(sys_get_temp_dir(), 'pie_additional_php_ini_path');
        unlink($additionalPhpIniDirectory);
        mkdir($additionalPhpIniDirectory, recursive: true);

        $expectedIniFile = $additionalPhpIniDirectory . DIRECTORY_SEPARATOR . '80-foobar.ini';

        $this->mockPhpBinary
            ->expects(self::once())
            ->method('additionalIniDirectory')
            ->willReturn($additionalPhpIniDirectory);

        $this->checkAndAddExtensionToIniIfNeeded
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                $expectedIniFile,
                $this->targetPlatform,
                $this->downloadedPackage,
                $this->io,
            )
            ->willReturn(true);

        self::assertTrue($this->standardAdditionalPhpIniDirectory->setup(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $this->io,
        ));
        self::assertFileExists($expectedIniFile);

        unlink($expectedIniFile);
        rmdir($additionalPhpIniDirectory);
    }

    public function testReturnsFalseAndRemovesPieCreatedIniFileWhenCheckAndAddExtensionIsInvoked(): void
    {
        $additionalPhpIniDirectory = tempnam(sys_get_temp_dir(), 'pie_additional_php_ini_path');
        unlink($additionalPhpIniDirectory);
        mkdir($additionalPhpIniDirectory, recursive: true);

        $expectedIniFile = $additionalPhpIniDirectory . DIRECTORY_SEPARATOR . '80-foobar.ini';

        $this->mockPhpBinary
            ->expects(self::once())
            ->method('additionalIniDirectory')
            ->willReturn($additionalPhpIniDirectory);

        $this->checkAndAddExtensionToIniIfNeeded
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                $expectedIniFile,
                $this->targetPlatform,
                $this->downloadedPackage,
                $this->io,
            )
            ->willReturn(false);

        self::assertFalse($this->standardAdditionalPhpIniDirectory->setup(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $this->io,
        ));
        self::assertFileDoesNotExist($expectedIniFile);

        rmdir($additionalPhpIniDirectory);
    }

    public function testReturnsFalseAndLeavesNonPieCreatedIniFileWhenCheckAndAddExtensionIsInvoked(): void
    {
        $additionalPhpIniDirectory = tempnam(sys_get_temp_dir(), 'pie_additional_php_ini_path');
        unlink($additionalPhpIniDirectory);
        mkdir($additionalPhpIniDirectory, recursive: true);

        $expectedIniFile = $additionalPhpIniDirectory . DIRECTORY_SEPARATOR . '80-foobar.ini';
        touch($expectedIniFile);

        $this->mockPhpBinary
            ->expects(self::once())
            ->method('additionalIniDirectory')
            ->willReturn($additionalPhpIniDirectory);

        $this->checkAndAddExtensionToIniIfNeeded
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                $expectedIniFile,
                $this->targetPlatform,
                $this->downloadedPackage,
                $this->io,
            )
            ->willReturn(false);

        self::assertFalse($this->standardAdditionalPhpIniDirectory->setup(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $this->io,
        ));
        self::assertFileExists($expectedIniFile);

        unlink($expectedIniFile);
        rmdir($additionalPhpIniDirectory);
    }
}
