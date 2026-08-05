<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing;

use Composer\IO\BufferIO;
use Composer\Package\CompletePackageInterface;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\File\BinaryFile;
use Php\Pie\Installing\Ini\SetupIniApproach;
use Php\Pie\Installing\SetupIniFile;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\StreamOutput;

#[CoversClass(SetupIniFile::class)]
final class SetupIniFileTest extends TestCase
{
    private DownloadedPackage $downloadedPackage;
    private BinaryFile $binaryFile;
    private TargetPlatform $targetPlatform;

    protected function setUp(): void
    {
        parent::setUp();

        $package = new Package(
            $this->createMock(CompletePackageInterface::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('xdebug'),
            'foo/bar',
            '1.2.3',
            null,
        );

        $this->downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath($package, __DIR__);
        $this->binaryFile        = new BinaryFile(__FILE__, 'abc123');

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        /** @phpstan-ignore property.notFound */
        (fn () => $this->phpBinaryPath = '/usr/bin/php')
            ->bindTo($phpBinaryPath, PhpBinaryPath::class)();

        $this->targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::NonThreadSafe,
            1,
            null,
            null,
        );
    }

    public function testSuccessfulSetupPrintsEnabledMessage(): void
    {
        $setupIniApproach = $this->createMock(SetupIniApproach::class);
        $setupIniApproach->method('canBeUsed')->willReturn(true);
        $setupIniApproach->method('setup')->willReturn(true);

        $io = new BufferIO();

        (new SetupIniFile($setupIniApproach))(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $io,
            true,
        );

        $output = $io->getOutput();

        self::assertStringContainsString('is enabled and loaded in', $output);
        self::assertStringNotContainsString('Extension has NOT been automatically enabled.', $output);
        self::assertStringNotContainsString('Automatic extension enabling was skipped.', $output);
    }

    public function testDeliberateSkipDoesNotPrintWarning(): void
    {
        $setupIniApproach = $this->createMock(SetupIniApproach::class);
        $setupIniApproach->expects(self::never())->method('canBeUsed');
        $setupIniApproach->expects(self::never())->method('setup');

        $io = new BufferIO('', StreamOutput::VERBOSITY_VERBOSE);

        (new SetupIniFile($setupIniApproach))(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $io,
            false,
        );

        $output = $io->getOutput();

        self::assertStringContainsString('Automatic extension enabling was skipped.', $output);
        self::assertStringNotContainsString('Extension has NOT been automatically enabled.', $output);
        self::assertStringNotContainsString('You must now add', $output);
    }

    public function testAttemptedButFailedSetupPrintsWarning(): void
    {
        $setupIniApproach = $this->createMock(SetupIniApproach::class);
        $setupIniApproach->method('canBeUsed')->willReturn(false);

        $io = new BufferIO();

        (new SetupIniFile($setupIniApproach))(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $io,
            true,
        );

        $output = $io->getOutput();

        self::assertStringContainsString('Extension has NOT been automatically enabled.', $output);
        self::assertStringContainsString('You must now add "extension=xdebug" to your php.ini', $output);
        self::assertStringNotContainsString('Automatic extension enabling was skipped.', $output);
    }
}
