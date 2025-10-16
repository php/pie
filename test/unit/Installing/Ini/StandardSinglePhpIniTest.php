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
use Php\Pie\Installing\Ini\StandardSinglePhpIni;
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

#[CoversClass(StandardSinglePhpIni::class)]
final class StandardSinglePhpIniTest extends TestCase
{
    private const INI_FILE = __DIR__ . '/../../../assets/example_ini_files/with_commented_extension.ini';

    private BufferIO $io;
    private PhpBinaryPath&MockObject $mockPhpBinary;
    private CheckAndAddExtensionToIniIfNeeded&MockObject $checkAndAddExtensionToIniIfNeeded;
    private TargetPlatform $targetPlatform;
    private DownloadedPackage $downloadedPackage;
    private BinaryFile $binaryFile;
    private StandardSinglePhpIni $standardSinglePhpIni;

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

        $this->standardSinglePhpIni = new StandardSinglePhpIni(
            $this->checkAndAddExtensionToIniIfNeeded,
        );
    }

    public function testCannotBeUsedWithNoDefinedPhpIni(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('loadedIniConfigurationFile')
            ->willReturn(null);

        self::assertFalse($this->standardSinglePhpIni->canBeUsed($this->targetPlatform));
    }

    public function testCanBeUsedWithDefinedPhpIni(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('loadedIniConfigurationFile')
            ->willReturn('/path/to/php.ini');

        self::assertTrue($this->standardSinglePhpIni->canBeUsed($this->targetPlatform));
    }

    public function testSetupReturnsWhenIniFileIsNotSet(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('loadedIniConfigurationFile')
            ->willReturn(null);

        $this->checkAndAddExtensionToIniIfNeeded
            ->expects(self::never())
            ->method('__invoke');

        self::assertFalse($this->standardSinglePhpIni->setup(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $this->io,
        ));
    }

    public function testReturnsTrueWhenCheckAndAddExtensionIsInvoked(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('loadedIniConfigurationFile')
            ->willReturn(self::INI_FILE);

        $this->checkAndAddExtensionToIniIfNeeded
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                self::INI_FILE,
                $this->targetPlatform,
                $this->downloadedPackage,
                $this->io,
            )
            ->willReturn(true);

        self::assertTrue($this->standardSinglePhpIni->setup(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $this->io,
        ));
    }

    public function testReturnsFalseWhenCheckAndAddExtensionIsInvoked(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('loadedIniConfigurationFile')
            ->willReturn(self::INI_FILE);

        $this->checkAndAddExtensionToIniIfNeeded
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                self::INI_FILE,
                $this->targetPlatform,
                $this->downloadedPackage,
                $this->io,
            )
            ->willReturn(false);

        self::assertFalse($this->standardSinglePhpIni->setup(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $this->io,
        ));
    }
}
