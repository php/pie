<?php

declare(strict_types=1);

namespace Php\PieUnitTest\SelfManage\BuildTools;

use Composer\IO\BufferIO;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\PackageManager;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use Php\Pie\SelfManage\BuildTools\BinaryBuildToolFinder;
use Php\Pie\SelfManage\BuildTools\CheckAllBuildTools;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(CheckAllBuildTools::class)]
final class CheckAllBuildToolsTest extends TestCase
{
    private static function targetPlatform(): TargetPlatform
    {
        return new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            PhpBinaryPath::fromCurrentProcess(),
            Architecture::x86_64,
            ThreadSafetyMode::NonThreadSafe,
            1,
            null,
            null,
        );
    }

    public function testStatusesReportsFoundToolWithNoPackageName(): void
    {
        $checkAllBuildTools = new CheckAllBuildTools([
            new BinaryBuildToolFinder('echo', [PackageManager::Test->value => 'coreutils']),
        ]);

        $statuses = $checkAllBuildTools->statuses(self::targetPlatform(), PackageManager::Test);

        self::assertCount(1, $statuses);
        self::assertSame('echo', $statuses[0]->toolNames);
        self::assertTrue($statuses[0]->found);
        self::assertNull($statuses[0]->packageName);
    }

    public function testStatusesReportsMissingToolWithPackageNameWhenPackageManagerKnown(): void
    {
        $checkAllBuildTools = new CheckAllBuildTools([
            new BinaryBuildToolFinder('bloop', [PackageManager::Test->value => 'coreutils']),
        ]);

        $statuses = $checkAllBuildTools->statuses(self::targetPlatform(), PackageManager::Test);

        self::assertCount(1, $statuses);
        self::assertSame('bloop', $statuses[0]->toolNames);
        self::assertFalse($statuses[0]->found);
        self::assertSame('coreutils', $statuses[0]->packageName);
    }

    public function testStatusesReportsMissingToolWithoutPackageNameWhenNoPackageManagerKnown(): void
    {
        $checkAllBuildTools = new CheckAllBuildTools([
            new BinaryBuildToolFinder('bloop', [PackageManager::Test->value => 'coreutils']),
        ]);

        $statuses = $checkAllBuildTools->statuses(self::targetPlatform(), null);

        self::assertCount(1, $statuses);
        self::assertSame('bloop', $statuses[0]->toolNames);
        self::assertFalse($statuses[0]->found);
        self::assertNull($statuses[0]->packageName);
    }

    public function testCheckDoesNothingWhenAllBuildToolsAreFound(): void
    {
        $io = new BufferIO(verbosity: OutputInterface::VERBOSITY_VERY_VERBOSE);

        $checkAllBuildTools = new CheckAllBuildTools([
            new BinaryBuildToolFinder('echo', [PackageManager::Test->value => 'coreutils']),
        ]);

        $checkAllBuildTools->check(
            $io,
            PackageManager::Test,
            self::targetPlatform(),
            false,
        );

        $outputString = $io->getOutput();
        self::assertStringContainsString('Checking if all build tools are installed.', $outputString);
        self::assertStringContainsString('Build tool echo is installed.', $outputString);
        self::assertStringContainsString('All build tools found.', $outputString);
    }

    public function testCheckInstallsMissingToolWhenPromptedInInteractiveMode(): void
    {
        $io = new BufferIO(verbosity: OutputInterface::VERBOSITY_VERY_VERBOSE);
        $io->setUserInputs(['y']); // answer yes to install build tools

        $checkAllBuildTools = new CheckAllBuildTools([
            new BinaryBuildToolFinder('bloop', [PackageManager::Test->value => 'coreutils']),
        ]);

        $checkAllBuildTools->check(
            $io,
            PackageManager::Test,
            self::targetPlatform(),
            false,
        );

        $outputString = $io->getOutput();
        self::assertStringContainsString('Checking if all build tools are installed.', $outputString);
        self::assertStringContainsString('The following build tools are missing: bloop', $outputString);
        self::assertStringContainsString('Would you like to install them now? [y/N]', $outputString);
        self::assertStringContainsString('The following command will be run: echo "fake installing coreutils"', $outputString);
        self::assertStringContainsString('Missing build tools have been installed.', $outputString);
    }

    public function testCheckDoesNotInstallToolsWhenInNonInteractiveModeAndFlagNotProvided(): void
    {
        $io = new BufferIO(verbosity: OutputInterface::VERBOSITY_VERY_VERBOSE);

        $checkAllBuildTools = new CheckAllBuildTools([
            new BinaryBuildToolFinder('bloop', [PackageManager::Test->value => 'coreutils']),
        ]);

        $checkAllBuildTools->check(
            $io,
            PackageManager::Test,
            self::targetPlatform(),
            false,
        );

        $outputString = $io->getOutput();
        self::assertStringContainsString('Checking if all build tools are installed.', $outputString);
        self::assertStringContainsString('The following build tools are missing: bloop', $outputString);
        self::assertStringContainsString('You are not running in interactive mode, and you did not provide the --auto-install-build-tools flag.', $outputString);
        self::assertStringContainsString('You may need to run: echo "fake installing coreutils"', $outputString);
    }

    public function testCheckInstallsMissingToolInNonInteractiveModeAndFlagIsProvided(): void
    {
        $io = new BufferIO(verbosity: OutputInterface::VERBOSITY_VERY_VERBOSE);

        $checkAllBuildTools = new CheckAllBuildTools([
            new BinaryBuildToolFinder('bloop', [PackageManager::Test->value => 'coreutils']),
        ]);

        $checkAllBuildTools->check(
            $io,
            PackageManager::Test,
            self::targetPlatform(),
            true,
        );

        $outputString = $io->getOutput();
        self::assertStringContainsString('Checking if all build tools are installed.', $outputString);
        self::assertStringContainsString('The following build tools are missing: bloop', $outputString);
        self::assertStringContainsString('The following command will be run: echo "fake installing coreutils"', $outputString);
        self::assertStringContainsString('Missing build tools have been installed.', $outputString);
    }
}
