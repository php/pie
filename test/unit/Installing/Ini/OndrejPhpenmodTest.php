<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\Ini;

use Composer\Package\CompletePackageInterface;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\File\BinaryFile;
use Php\Pie\Installing\Ini\CheckAndAddExtensionToIniIfNeeded;
use Php\Pie\Installing\Ini\OndrejPhpenmod;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const DIRECTORY_SEPARATOR;

#[CoversClass(OndrejPhpenmod::class)]
final class OndrejPhpenmodTest extends TestCase
{
    private const NON_EXISTENT_PHPENMOD            = 'something-that-should-not-be-in-path';
    private const NON_EXISTENT_MODS_AVAILABLE_PATH = '/some/path/that/should/not/exist';
    private const GOOD_PHPENMOD                    = __DIR__ . '/../../../assets/phpenmod/good';
    private const BAD_PHPENMOD                     = __DIR__ . '/../../../assets/phpenmod/bad';

    private BufferedOutput $output;
    private PhpBinaryPath&MockObject $mockPhpBinary;
    private CheckAndAddExtensionToIniIfNeeded&MockObject $checkAndAddExtensionToIniIfNeeded;
    private TargetPlatform $targetPlatform;
    private DownloadedPackage $downloadedPackage;
    private BinaryFile $binaryFile;

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
    }

    #[RequiresOperatingSystemFamily('Windows')]
    public function testCanBeUsedReturnsFalseOnWindows(): void
    {
        self::assertFalse(
            (new OndrejPhpenmod(
                $this->checkAndAddExtensionToIniIfNeeded,
                self::GOOD_PHPENMOD,
                self::NON_EXISTENT_MODS_AVAILABLE_PATH,
            ))->canBeUsed($this->targetPlatform),
        );
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testCanBeUsedReturnsFalseWhenPhpenmodNotInPath(): void
    {
        self::assertFalse(
            (new OndrejPhpenmod(
                $this->checkAndAddExtensionToIniIfNeeded,
                self::NON_EXISTENT_PHPENMOD,
                self::NON_EXISTENT_MODS_AVAILABLE_PATH,
            ))->canBeUsed($this->targetPlatform),
        );
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testCanBeUsedReturnsTrueWhenPhpenmodInPath(): void
    {
        self::assertTrue(
            (new OndrejPhpenmod(
                $this->checkAndAddExtensionToIniIfNeeded,
                self::GOOD_PHPENMOD,
                self::NON_EXISTENT_MODS_AVAILABLE_PATH,
            ))->canBeUsed($this->targetPlatform),
        );
    }

    #[RequiresOperatingSystemFamily('Windows')]
    public function testSetupReturnsFalseOnWindows(): void
    {
        $this->checkAndAddExtensionToIniIfNeeded
            ->expects(self::never())
            ->method('__invoke');

        self::assertFalse(
            (new OndrejPhpenmod(
                $this->checkAndAddExtensionToIniIfNeeded,
                self::GOOD_PHPENMOD,
                self::NON_EXISTENT_MODS_AVAILABLE_PATH,
            ))->setup(
                $this->targetPlatform,
                $this->downloadedPackage,
                $this->binaryFile,
                $this->output,
            ),
        );
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testSetupReturnsFalseWhenPhpenmodNotInPath(): void
    {
        $this->checkAndAddExtensionToIniIfNeeded
            ->expects(self::never())
            ->method('__invoke');

        self::assertFalse(
            (new OndrejPhpenmod(
                $this->checkAndAddExtensionToIniIfNeeded,
                self::NON_EXISTENT_PHPENMOD,
                self::NON_EXISTENT_MODS_AVAILABLE_PATH,
            ))->setup(
                $this->targetPlatform,
                $this->downloadedPackage,
                $this->binaryFile,
                $this->output,
            ),
        );
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testSetupReturnsFalseWhenAdditionalPhpIniPathNotSet(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('additionalIniDirectory')
            ->willReturn(null);

        $this->checkAndAddExtensionToIniIfNeeded
            ->expects(self::never())
            ->method('__invoke');

        self::assertFalse(
            (new OndrejPhpenmod(
                $this->checkAndAddExtensionToIniIfNeeded,
                self::GOOD_PHPENMOD,
                self::NON_EXISTENT_MODS_AVAILABLE_PATH,
            ))->setup(
                $this->targetPlatform,
                $this->downloadedPackage,
                $this->binaryFile,
                $this->output,
            ),
        );

        self::assertStringContainsString(
            'Additional INI file path was not set - may not be Ondrej PHP repo',
            $this->output->fetch(),
        );
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testSetupReturnsFalseWhenModsAvailablePathDoesNotExist(): void
    {
        $this->mockPhpBinary
            ->method('additionalIniDirectory')
            ->willReturn('/value/does/not/matter');

        $this->checkAndAddExtensionToIniIfNeeded
            ->expects(self::never())
            ->method('__invoke');

        self::assertFalse(
            (new OndrejPhpenmod(
                $this->checkAndAddExtensionToIniIfNeeded,
                self::GOOD_PHPENMOD,
                self::NON_EXISTENT_MODS_AVAILABLE_PATH,
            ))->setup(
                $this->targetPlatform,
                $this->downloadedPackage,
                $this->binaryFile,
                $this->output,
            ),
        );

        self::assertStringContainsString(
            'Mods available path ' . self::NON_EXISTENT_MODS_AVAILABLE_PATH . ' does not exist',
            $this->output->fetch(),
        );
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testSetupReturnsFalseWhenModsAvailablePathNotADirectory(): void
    {
        $this->mockPhpBinary
            ->method('additionalIniDirectory')
            ->willReturn('/value/does/not/matter');

        $this->checkAndAddExtensionToIniIfNeeded
            ->expects(self::never())
            ->method('__invoke');

        self::assertFalse(
            (new OndrejPhpenmod(
                $this->checkAndAddExtensionToIniIfNeeded,
                self::GOOD_PHPENMOD,
                __FILE__,
            ))->setup(
                $this->targetPlatform,
                $this->downloadedPackage,
                $this->binaryFile,
                $this->output,
            ),
        );

        self::assertStringContainsString(
            'Mods available path ' . __FILE__ . ' is not a directory',
            $this->output->fetch(),
        );
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testSetupReturnsFalseWhenModsAvailablePathNotWritable(): void
    {
        if (TargetPlatform::isRunningAsRoot()) {
            self::markTestSkipped('Test cannot be run as root, as root can always write files');
        }

        $modsAvailablePath = tempnam(sys_get_temp_dir(), 'pie_test_mods_available_path');
        unlink($modsAvailablePath);
        mkdir($modsAvailablePath, 000, true);

        $expectedIniFile = $modsAvailablePath . DIRECTORY_SEPARATOR . 'foobar.ini';

        $this->mockPhpBinary
            ->method('additionalIniDirectory')
            ->willReturn('/value/does/not/matter');

        $this->checkAndAddExtensionToIniIfNeeded
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                $expectedIniFile,
                $this->targetPlatform,
                $this->downloadedPackage,
                $this->output,
                self::isType(IsType::TYPE_CALLABLE),
            )
            ->willReturnCallback(
            /** @param callable():bool $additionalEnableStep */
                static function (
                    string $iniFile,
                    TargetPlatform $targetPlatform,
                    DownloadedPackage $downloadedPackage,
                    OutputInterface $output,
                    callable $additionalEnableStep,
                ): bool {
                    return $additionalEnableStep();
                },
            );

        self::assertTrue(
            (new OndrejPhpenmod(
                $this->checkAndAddExtensionToIniIfNeeded,
                self::GOOD_PHPENMOD,
                $modsAvailablePath,
            ))->setup(
                $this->targetPlatform,
                $this->downloadedPackage,
                $this->binaryFile,
                $this->output,
            ),
        );

        rmdir($modsAvailablePath);
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testSetupReturnsFalseAndRemovesPieCreatedIniFileWhenPhpenmodAdditionalStepFails(): void
    {
        $modsAvailablePath = tempnam(sys_get_temp_dir(), 'pie_test_mods_available_path');
        unlink($modsAvailablePath);
        mkdir($modsAvailablePath, recursive: true);

        $expectedIniFile = $modsAvailablePath . DIRECTORY_SEPARATOR . 'foobar.ini';

        $this->mockPhpBinary
            ->method('additionalIniDirectory')
            ->willReturn('/value/does/not/matter');

        $this->checkAndAddExtensionToIniIfNeeded
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                $expectedIniFile,
                $this->targetPlatform,
                $this->downloadedPackage,
                $this->output,
                self::isType(IsType::TYPE_CALLABLE),
            )
            ->willReturnCallback(
                /** @param callable():bool $additionalEnableStep */
                static function (
                    string $iniFile,
                    TargetPlatform $targetPlatform,
                    DownloadedPackage $downloadedPackage,
                    OutputInterface $output,
                    callable $additionalEnableStep,
                ): bool {
                    return $additionalEnableStep();
                },
            );

        self::assertFalse(
            (new OndrejPhpenmod(
                $this->checkAndAddExtensionToIniIfNeeded,
                self::BAD_PHPENMOD,
                $modsAvailablePath,
            ))->setup(
                $this->targetPlatform,
                $this->downloadedPackage,
                $this->binaryFile,
                $this->output,
            ),
        );

        self::assertFileDoesNotExist($expectedIniFile);

        self::assertStringContainsString(
            'something bad happened',
            $this->output->fetch(),
        );

        rmdir($modsAvailablePath);
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testSetupReturnsFalseAndRemovesPieCreatedIniFileWhenCheckAndAddExtensionFails(): void
    {
        $modsAvailablePath = tempnam(sys_get_temp_dir(), 'pie_test_mods_available_path');
        unlink($modsAvailablePath);
        mkdir($modsAvailablePath, recursive: true);

        $expectedIniFile = $modsAvailablePath . DIRECTORY_SEPARATOR . 'foobar.ini';

        $this->mockPhpBinary
            ->method('additionalIniDirectory')
            ->willReturn('/value/does/not/matter');

        $this->checkAndAddExtensionToIniIfNeeded
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                $expectedIniFile,
                $this->targetPlatform,
                $this->downloadedPackage,
                $this->output,
                self::isType(IsType::TYPE_CALLABLE),
            )
            ->willReturn(false);

        self::assertFalse(
            (new OndrejPhpenmod(
                $this->checkAndAddExtensionToIniIfNeeded,
                self::GOOD_PHPENMOD,
                $modsAvailablePath,
            ))->setup(
                $this->targetPlatform,
                $this->downloadedPackage,
                $this->binaryFile,
                $this->output,
            ),
        );

        self::assertFileDoesNotExist($expectedIniFile);

        rmdir($modsAvailablePath);
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testSetupReturnsTrueWhenExtensionIsEnabled(): void
    {
        $modsAvailablePath = tempnam(sys_get_temp_dir(), 'pie_test_mods_available_path');
        unlink($modsAvailablePath);
        mkdir($modsAvailablePath, recursive: true);

        $expectedIniFile = $modsAvailablePath . DIRECTORY_SEPARATOR . 'foobar.ini';

        $this->mockPhpBinary
            ->method('additionalIniDirectory')
            ->willReturn('/value/does/not/matter');

        $this->checkAndAddExtensionToIniIfNeeded
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                $expectedIniFile,
                $this->targetPlatform,
                $this->downloadedPackage,
                $this->output,
                self::isType(IsType::TYPE_CALLABLE),
            )
            ->willReturnCallback(
            /** @param callable():bool $additionalEnableStep */
                static function (
                    string $iniFile,
                    TargetPlatform $targetPlatform,
                    DownloadedPackage $downloadedPackage,
                    OutputInterface $output,
                    callable $additionalEnableStep,
                ): bool {
                    return $additionalEnableStep();
                },
            );

        self::assertTrue(
            (new OndrejPhpenmod(
                $this->checkAndAddExtensionToIniIfNeeded,
                self::GOOD_PHPENMOD,
                $modsAvailablePath,
            ))->setup(
                $this->targetPlatform,
                $this->downloadedPackage,
                $this->binaryFile,
                $this->output,
            ),
        );

        self::assertFileExists($expectedIniFile);

        unlink($expectedIniFile);
        rmdir($modsAvailablePath);
    }
}
