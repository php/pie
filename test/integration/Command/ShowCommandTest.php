<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use Composer\Util\Platform;
use InvalidArgumentException;
use Php\Pie\Command\InstallCommand;
use Php\Pie\Command\ShowCommand;
use Php\Pie\ComposerIntegration\PieJsonEditor;
use Php\Pie\Container;
use Php\Pie\Platform as PiePlatform;
use Php\Pie\Util\Process;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Webmozart\Assert\Assert;

use function get_loaded_extensions;
use function getenv;
use function phpversion;
use function Safe\putenv;
use function str_contains;
use function sys_get_temp_dir;

use const DIRECTORY_SEPARATOR;
use const PHP_BINARY;
use const PHP_VERSION_ID;

#[CoversClass(ShowCommand::class)]
final class ShowCommandTest extends TestCase
{
    private const TEST_PACKAGE = 'asgrim/example-pie-extension';

    private CommandTester $commandTester;

    public function setUp(): void
    {
        $this->commandTester = new CommandTester(Container::testFactory()->get(ShowCommand::class));
    }

    public function testExecute(): void
    {
        $this->commandTester->execute(['--all' => true]);

        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();

        foreach (get_loaded_extensions() as $extension) {
            self::assertStringContainsString(
                $extension . ':' . (phpversion($extension) === false ? '0' : phpversion($extension)),
                $outputString,
            );
        }
    }

    public function testExecuteWithExplicitTargetPhpWhenRunningPhpCannotBeFound(): void
    {
        if (Platform::isWindows()) {
            $targetPhpOption = ['--with-php-path' => PHP_BINARY];
        } else {
            try {
                $phpConfig = Process::run(['which', 'php-config']);
                Assert::stringNotEmpty($phpConfig);
            } catch (ProcessFailedException | InvalidArgumentException) {
                self::markTestSkipped('This test can only run on systems with php-config');
            }

            $targetPhpOption = ['--with-php-config' => $phpConfig];
        }

        $originalPhpBinaryEnv = getenv('PHP_BINARY');
        putenv('PHP_BINARY=' . sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php-that-does-not-exist');

        try {
            $this->commandTester->execute($targetPhpOption);
        } finally {
            putenv($originalPhpBinaryEnv === false ? 'PHP_BINARY' : 'PHP_BINARY=' . $originalPhpBinaryEnv);
        }

        $this->commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('Target PHP installation:', $this->commandTester->getDisplay());
    }

    public function testExecuteWithAvailableConstrainedUpdates(): void
    {
        if (PHP_VERSION_ID >= 80500) {
            self::markTestSkipped('This test can only run on PHP 8.4 or lower');
        }

        try {
            $phpConfig = Process::run(['which', 'php-config']);
            Assert::stringNotEmpty($phpConfig);
        } catch (ProcessFailedException | InvalidArgumentException) {
            self::markTestSkipped('This test can only run on systems with php-config');
        }

        $installCommand = new CommandTester(Container::testFactory()->get(InstallCommand::class));
        $installCommand->execute([
            'requested-package-and-version' => [self::TEST_PACKAGE . ':2.0.2'],
            '--with-php-config' => $phpConfig,
        ]);
        $installCommand->assertCommandIsSuccessful();

        $outputString = $installCommand->getDisplay();

        if (str_contains($outputString, 'NOT been automatically')) {
            self::markTestSkipped('PIE couldn\'t automatically enable the extension');
        }

        PieJsonEditor::fromTargetPlatform(
            PiePlatform\TargetPlatform::fromPhpBinaryPath(
                PiePlatform\TargetPhp\PhpBinaryPath::fromPhpConfigExecutable(
                    $phpConfig,
                ),
                1,
                null,
            ),
        )
            ->addRequire(self::TEST_PACKAGE, '^2.0');

        $this->commandTester->execute(['--with-php-config' => $phpConfig]);
        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();

        self::assertStringMatchesFormat(
            '%Aexample_pie_extension:%S (from %S asgrim/example-pie-extension:2.0.2%S), upgradable to %S (within %S)%A',
            $outputString,
        );
    }

    public function testExecuteWithAvailableUnconstrainedUpdates(): void
    {
        if (PHP_VERSION_ID >= 80500) {
            self::markTestSkipped('This test can only run on PHP 8.4 or lower');
        }

        try {
            $phpConfig = Process::run(['which', 'php-config']);
            Assert::stringNotEmpty($phpConfig);
        } catch (ProcessFailedException | InvalidArgumentException) {
            self::markTestSkipped('This test can only run on systems with php-config');
        }

        $installCommand = new CommandTester(Container::testFactory()->get(InstallCommand::class));
        $installCommand->execute([
            'requested-package-and-version' => [self::TEST_PACKAGE . ':2.0.2'],
            '--with-php-config' => $phpConfig,
        ]);
        $installCommand->assertCommandIsSuccessful();

        $outputString = $installCommand->getDisplay();

        if (str_contains($outputString, 'NOT been automatically')) {
            self::markTestSkipped('PIE couldn\'t automatically enable the extension');
        }

        PieJsonEditor::fromTargetPlatform(
            PiePlatform\TargetPlatform::fromPhpBinaryPath(
                PiePlatform\TargetPhp\PhpBinaryPath::fromPhpConfigExecutable(
                    $phpConfig,
                ),
                1,
                null,
            ),
        )
            ->addRequire(self::TEST_PACKAGE, '^2.0,<=2.0.3');

        $this->commandTester->execute(['--with-php-config' => $phpConfig]);
        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();

        self::assertStringMatchesFormat(
            '%Aexample_pie_extension:%S (from %S asgrim/example-pie-extension:2.0.2%S), upgradable to %S (within %S), latest version is %A',
            $outputString,
        );
    }

    public function testExecuteWithOnlyUnconstrainedUpdates(): void
    {
        if (PHP_VERSION_ID >= 80500) {
            self::markTestSkipped('This test can only run on PHP 8.4 or lower');
        }

        try {
            $phpConfig = Process::run(['which', 'php-config']);
            Assert::stringNotEmpty($phpConfig);
        } catch (ProcessFailedException | InvalidArgumentException) {
            self::markTestSkipped('This test can only run on systems with php-config');
        }

        $installCommand = new CommandTester(Container::testFactory()->get(InstallCommand::class));
        $installCommand->execute([
            'requested-package-and-version' => [self::TEST_PACKAGE . ':2.0.2'],
            '--with-php-config' => $phpConfig,
        ]);
        $installCommand->assertCommandIsSuccessful();

        $outputString = $installCommand->getDisplay();

        if (str_contains($outputString, 'NOT been automatically')) {
            self::markTestSkipped('PIE couldn\'t automatically enable the extension');
        }

        PieJsonEditor::fromTargetPlatform(
            PiePlatform\TargetPlatform::fromPhpBinaryPath(
                PiePlatform\TargetPhp\PhpBinaryPath::fromPhpConfigExecutable(
                    $phpConfig,
                ),
                1,
                null,
            ),
        )
            ->addRequire(self::TEST_PACKAGE, '^2.0,<2.0.3');

        $this->commandTester->execute(['--with-php-config' => $phpConfig]);
        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();

        self::assertStringMatchesFormat(
            '%Aexample_pie_extension:%S (from %S asgrim/example-pie-extension:2.0.2%S), latest version is %A',
            $outputString,
        );
    }

    public function testExecuteShowsWarningWhenInstalledButNotInPieJson(): void
    {
        try {
            $phpConfig = Process::run(['which', 'php-config']);
            Assert::stringNotEmpty($phpConfig);
        } catch (ProcessFailedException | InvalidArgumentException) {
            self::markTestSkipped('This test can only run on systems with php-config');
        }

        $installCommand = new CommandTester(Container::testFactory()->get(InstallCommand::class));
        $installCommand->execute([
            'requested-package-and-version' => [self::TEST_PACKAGE . ':2.0.2'],
            '--with-php-config' => $phpConfig,
        ]);
        $installCommand->assertCommandIsSuccessful();

        $outputString = $installCommand->getDisplay();

        if (str_contains($outputString, 'NOT been automatically')) {
            self::markTestSkipped('PIE couldn\'t automatically enable the extension');
        }

        PieJsonEditor::fromTargetPlatform(
            PiePlatform\TargetPlatform::fromPhpBinaryPath(
                PiePlatform\TargetPhp\PhpBinaryPath::fromPhpConfigExecutable(
                    $phpConfig,
                ),
                1,
                null,
            ),
        )
            ->removeRequire(self::TEST_PACKAGE);

        $this->commandTester->execute(['--with-php-config' => $phpConfig]);
        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();

        self::assertStringMatchesFormat(
            '%Aexample_pie_extension:%S (from %S asgrim/example-pie-extension:2.0.2 %S- installed but does not exist in pie.json)%A',
            $outputString,
        );
    }
}
