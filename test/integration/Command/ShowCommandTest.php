<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use InvalidArgumentException;
use Php\Pie\Command\InstallCommand;
use Php\Pie\Command\ShowCommand;
use Php\Pie\ComposerIntegration\PieJsonEditor;
use Php\Pie\Container;
use Php\Pie\Platform as PiePlatform;
use Php\Pie\Util\Process;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Webmozart\Assert\Assert;

use function get_loaded_extensions;
use function phpversion;
use function str_contains;

use const PHP_VERSION_ID;

#[CoversClass(ShowCommand::class)]
final class ShowCommandTest extends TestCase
{
    private const TEST_PACKAGE = 'asgrim/example-pie-extension';

    private CommandTester $commandTester;

    public function setUp(): void
    {
        $this->commandTester = new CommandTester(Container::factory()->get(ShowCommand::class));
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

    public function testExecuteWithAvailableUpdates(): void
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

        $installCommand = new CommandTester(Container::factory()->get(InstallCommand::class));
        $installCommand->execute([
            'requested-package-and-version' => self::TEST_PACKAGE . ':2.0.2',
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
            ),
        )
            ->addRequire(self::TEST_PACKAGE, '^2.0');

        $this->commandTester->execute(['--with-php-config' => $phpConfig]);
        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();

        self::assertStringMatchesFormat(
            '%Aexample_pie_extension:%S (from %S asgrim/example-pie-extension:2.0.2%S) — new version %S available%A',
            $outputString,
        );
    }
}
