<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\Ini;

use Composer\Package\CompletePackageInterface;
use Php\Pie\BinaryFile;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\Ini\AddExtensionToTheIniFile;
use Php\Pie\Installing\Ini\IsExtensionAlreadyInTheIniFile;
use Php\Pie\Installing\Ini\StandardSinglePhpIni;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\Exception\ExtensionIsNotLoaded;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(StandardSinglePhpIni::class)]
final class StandardSinglePhpIniTest extends TestCase
{
    private const INI_FILE = __DIR__ . '/../../../assets/example_ini_files/with_commented_extension.ini';

    private BufferedOutput $output;
    private PhpBinaryPath&MockObject $mockPhpBinary;
    private IsExtensionAlreadyInTheIniFile&MockObject $isExtensionAlreadyInTheIniFile;
    private AddExtensionToTheIniFile&MockObject $addExtensionToTheIniFile;
    private TargetPlatform $targetPlatform;
    private DownloadedPackage $downloadedPackage;
    private BinaryFile $binaryFile;
    private StandardSinglePhpIni $standardSinglePhpIni;

    public function setUp(): void
    {
        parent::setUp();

        $this->output = new BufferedOutput(BufferedOutput::VERBOSITY_VERBOSE);

        $this->mockPhpBinary = $this->createMock(PhpBinaryPath::class);
        /**
         * @psalm-suppress PossiblyNullFunctionCall
         * @psalm-suppress UndefinedThisPropertyAssignment
         */
        (fn () => $this->phpBinaryPath = '/path/to/php')
            ->bindTo($this->mockPhpBinary, PhpBinaryPath::class)();

        $this->isExtensionAlreadyInTheIniFile = $this->createMock(IsExtensionAlreadyInTheIniFile::class);
        $this->addExtensionToTheIniFile       = $this->createMock(AddExtensionToTheIniFile::class);

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
                [],
                true,
                true,
                null,
                null,
                null,
                99,
            ),
            '/path/to/extracted/source',
        );

        $this->binaryFile = new BinaryFile('/path/to/compiled/extension.so', 'fake checksum');

        $this->standardSinglePhpIni = new StandardSinglePhpIni(
            $this->isExtensionAlreadyInTheIniFile,
            $this->addExtensionToTheIniFile,
        );
    }

    public function testCannotBeUsedWithNoDefinedPhpIni(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('phpinfo')
            ->willReturn('Loaded Configuration File => (none)');

        self::assertFalse($this->standardSinglePhpIni->canBeUsed($this->targetPlatform));
    }

    public function testCanBeUsedWithDefinedPhpIni(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('phpinfo')
            ->willReturn('Loaded Configuration File => /path/to/php.ini');

        self::assertTrue($this->standardSinglePhpIni->canBeUsed($this->targetPlatform));
    }

    public function testSetupReturnsWhenCannotBeUsed(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('phpinfo')
            ->willReturn('Loaded Configuration File => (none)');

        self::assertFalse($this->standardSinglePhpIni->setup(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $this->output,
        ));
    }

    public function testSetupWhenIniFileDoesNotExist(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('phpinfo')
            ->willReturn('Loaded Configuration File => /path/to/non/existent/php.ini');

        $this->isExtensionAlreadyInTheIniFile
            ->expects(self::never())
            ->method('__invoke');

        $this->mockPhpBinary
            ->expects(self::never())
            ->method('assertExtensionIsLoadedInRuntime');

        $this->addExtensionToTheIniFile
            ->expects(self::never())
            ->method('__invoke');

        self::assertFalse($this->standardSinglePhpIni->setup(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $this->output,
        ));

        self::assertStringContainsString(
            'PHP is configured to use /path/to/non/existent/php.ini, but it did not exist, or is not readable by PIE.',
            $this->output->fetch(),
        );
    }

    public function testExtensionIsAlreadyEnabledButExtensionDoesNotLoad(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('phpinfo')
            ->willReturn('Loaded Configuration File => ' . self::INI_FILE);

        $this->isExtensionAlreadyInTheIniFile
            ->expects(self::once())
            ->method('__invoke')
            ->with(self::INI_FILE, $this->downloadedPackage->package->extensionName)
            ->willReturn(true);

        $this->mockPhpBinary
            ->expects(self::once())
            ->method('assertExtensionIsLoadedInRuntime')
            ->with($this->downloadedPackage->package->extensionName, $this->output)
            ->willThrowException(ExtensionIsNotLoaded::fromExpectedExtension(
                $this->mockPhpBinary,
                $this->downloadedPackage->package->extensionName,
            ));

        $this->addExtensionToTheIniFile
            ->expects(self::never())
            ->method('__invoke');

        self::assertFalse($this->standardSinglePhpIni->setup(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $this->output,
        ));

        $output = $this->output->fetch();
        self::assertStringContainsString(
            'Extension is already enabled in the INI file',
            $output,
        );
        self::assertStringContainsString(
            'Something went wrong verifying the foobar extension is enabled: Expected extension foobar to be loaded in PHP /path/to/php, but it was not detected.',
            $output,
        );
    }

    public function testExtensionIsAlreadyEnabledAndExtensionLoaded(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('phpinfo')
            ->willReturn('Loaded Configuration File => ' . self::INI_FILE);

        $this->isExtensionAlreadyInTheIniFile
            ->expects(self::once())
            ->method('__invoke')
            ->with(self::INI_FILE, $this->downloadedPackage->package->extensionName)
            ->willReturn(true);

        $this->mockPhpBinary
            ->expects(self::once())
            ->method('assertExtensionIsLoadedInRuntime')
            ->with($this->downloadedPackage->package->extensionName, $this->output);

        $this->addExtensionToTheIniFile
            ->expects(self::never())
            ->method('__invoke');

        self::assertTrue($this->standardSinglePhpIni->setup(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $this->output,
        ));

        $output = $this->output->fetch();
        self::assertStringContainsString(
            'Extension is already enabled in the INI file',
            $output,
        );
    }

    public function testExtensionIsNotYetAdded(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('phpinfo')
            ->willReturn('Loaded Configuration File => ' . self::INI_FILE);

        $this->isExtensionAlreadyInTheIniFile
            ->expects(self::once())
            ->method('__invoke')
            ->with(self::INI_FILE, $this->downloadedPackage->package->extensionName)
            ->willReturn(false);

        $this->mockPhpBinary
            ->expects(self::never())
            ->method('assertExtensionIsLoadedInRuntime');

        $this->addExtensionToTheIniFile
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                self::INI_FILE,
                $this->downloadedPackage->package,
                $this->targetPlatform->phpBinaryPath,
                $this->output,
            )
            ->willReturn(true);

        self::assertTrue($this->standardSinglePhpIni->setup(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $this->output,
        ));
    }

    public function testExtensionIsNotYetAddedButFailsToBeAdded(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('phpinfo')
            ->willReturn('Loaded Configuration File => ' . self::INI_FILE);

        $this->isExtensionAlreadyInTheIniFile
            ->expects(self::once())
            ->method('__invoke')
            ->with(self::INI_FILE, $this->downloadedPackage->package->extensionName)
            ->willReturn(false);

        $this->mockPhpBinary
            ->expects(self::never())
            ->method('assertExtensionIsLoadedInRuntime');

        $this->addExtensionToTheIniFile
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                self::INI_FILE,
                $this->downloadedPackage->package,
                $this->targetPlatform->phpBinaryPath,
                $this->output,
            )
            ->willReturn(false);

        self::assertFalse($this->standardSinglePhpIni->setup(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $this->output,
        ));
    }
}
