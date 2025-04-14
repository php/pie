<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\Ini;

use Composer\Package\CompletePackageInterface;
use Composer\Util\Filesystem;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\Ini\RemoveIniEntryWithFileGetContents;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

use function file_get_contents;
use function file_put_contents;
use function is_link;
use function mkdir;
use function realpath;
use function symlink;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;
use function unlink;

use const DIRECTORY_SEPARATOR;

#[CoversClass(RemoveIniEntryWithFileGetContents::class)]
final class RemoveIniEntryWithFileGetContentsTest extends TestCase
{
    private const INI_WITH_COMMENTED_EXTS = ";extension=foobar\n;zend_extension=foobar\n";
    private const INI_WITH_ACTIVE_EXTS    = "extension=foobar\nzend_extension=foobar\n";

    private string $iniFilePath;

    public function setUp(): void
    {
        parent::setUp();

        $this->iniFilePath = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . uniqid('pie_remove_ini_test', true);
        mkdir($this->iniFilePath);
        Assert::positiveInteger(file_put_contents(
            $this->iniFilePath . DIRECTORY_SEPARATOR . 'with_commented_exts.ini',
            self::INI_WITH_COMMENTED_EXTS,
        ));
        Assert::positiveInteger(file_put_contents(
            $this->iniFilePath . DIRECTORY_SEPARATOR . 'with_active_exts.ini',
            self::INI_WITH_ACTIVE_EXTS,
        ));
    }

    public function tearDown(): void
    {
        parent::tearDown();

        (new Filesystem())->remove($this->iniFilePath);
    }

    /** @return array<non-empty-string, array{0: ExtensionType, 1: non-empty-string}> */
    public static function extensionTypeProvider(): array
    {
        return [
            'phpModule' => [ExtensionType::PhpModule, "; extension=foobar ; removed by PIE\nzend_extension=foobar\n"],
            'zendExtension' => [ExtensionType::ZendExtension, "extension=foobar\n; zend_extension=foobar ; removed by PIE\n"],
        ];
    }

    #[DataProvider('extensionTypeProvider')]
    public function testRelevantIniFilesHaveExtensionRemoved(ExtensionType $extensionType, string $expectedActiveContent): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath
            ->method('loadedIniConfigurationFile')
            ->willReturn(null);
        $phpBinaryPath
            ->method('additionalIniDirectory')
            ->willReturn($this->iniFilePath);

        $package = new Package(
            $this->createMock(CompletePackageInterface::class),
            $extensionType,
            ExtensionName::normaliseFromString('foobar'),
            'foobar/foobar',
            '1.2.3',
            null,
        );

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
        );

        $affectedFiles = (new RemoveIniEntryWithFileGetContents())(
            $package,
            $targetPlatform,
            $this->createMock(OutputInterface::class),
        );

        self::assertSame(
            [$this->iniFilePath . DIRECTORY_SEPARATOR . 'with_active_exts.ini'],
            $affectedFiles,
        );

        self::assertSame(
            self::INI_WITH_COMMENTED_EXTS,
            file_get_contents($this->iniFilePath . DIRECTORY_SEPARATOR . 'with_commented_exts.ini'),
        );

        self::assertSame(
            $expectedActiveContent,
            file_get_contents($this->iniFilePath . DIRECTORY_SEPARATOR . 'with_active_exts.ini'),
        );
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testSymlinkedIniFilesAreResolved(): void
    {
        $realIni      = $this->iniFilePath . DIRECTORY_SEPARATOR . 'with_active_exts.ini';
        $symlinkedIni = tempnam(sys_get_temp_dir(), 'pie_ini_removal_test_link_');
        unlink($symlinkedIni);
        symlink($realIni, $symlinkedIni);
        self::assertTrue(is_link($symlinkedIni));

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath
            ->method('loadedIniConfigurationFile')
            ->willReturn($symlinkedIni);
        $phpBinaryPath
            ->method('additionalIniDirectory')
            ->willReturn(null);

        $package = new Package(
            $this->createMock(CompletePackageInterface::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foobar'),
            'foobar/foobar',
            '1.2.3',
            null,
        );

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
        );

        $affectedFiles = (new RemoveIniEntryWithFileGetContents())(
            $package,
            $targetPlatform,
            $this->createMock(OutputInterface::class),
        );

        self::assertSame(
            [$realIni],
            $affectedFiles,
        );

        self::assertTrue(is_link($symlinkedIni));

        $expectedIniContent = "; extension=foobar ; removed by PIE\nzend_extension=foobar\n";
        self::assertSame(
            $expectedIniContent,
            file_get_contents($realIni),
        );
        self::assertSame(
            $expectedIniContent,
            file_get_contents($symlinkedIni),
        );

        unlink($symlinkedIni);
    }
}
