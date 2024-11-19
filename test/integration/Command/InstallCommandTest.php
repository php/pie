<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use Composer\Util\Platform;
use Php\Pie\Command\InstallCommand;
use Php\Pie\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

use function array_combine;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_unshift;
use function assert;
use function file_exists;
use function is_executable;
use function is_file;
use function is_string;
use function is_writable;
use function preg_match;

#[CoversClass(InstallCommand::class)]
class InstallCommandTest extends TestCase
{
    private const TEST_PACKAGE = 'asgrim/example-pie-extension';

    private CommandTester $commandTester;

    public function setUp(): void
    {
        $this->commandTester = new CommandTester(Container::factory()->get(InstallCommand::class));
    }

    /**
     * @return array<string, array{0: string}>
     *
     * @psalm-suppress PossiblyUnusedMethod https://github.com/psalm/psalm-plugin-phpunit/issues/131
     */
    public static function phpPathProvider(): array
    {
        // data providers cannot return empty, even if the test is skipped
        if (Platform::isWindows()) {
            return ['skip' => ['skip']];
        }

        $possiblePhpConfigPaths = array_filter(
            [
                '/usr/bin/php-config',
                '/usr/bin/php-config8.3',
                '/usr/bin/php-config8.2',
                '/usr/bin/php-config8.1',
                '/usr/bin/php-config8.0',
                '/usr/bin/php-config7.4',
            ],
            static fn (string $phpConfigPath) => file_exists($phpConfigPath)
                && is_executable($phpConfigPath),
        );

        return array_combine(
            $possiblePhpConfigPaths,
            array_map(static fn (string $phpConfigPath) => [$phpConfigPath], $possiblePhpConfigPaths),
        );
    }

    #[DataProvider('phpPathProvider')]
    public function testInstallCommandWillInstallCompatibleExtensionNonWindows(string $phpConfigPath): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('This test can only run on non-Windows systems');
        }

        $this->commandTester->execute(
            ['requested-package-and-version' => self::TEST_PACKAGE, '--with-php-config' => $phpConfigPath],
            ['verbosity' => BufferedOutput::VERBOSITY_VERY_VERBOSE],
        );

        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();
        self::assertStringContainsString('Install complete: ', $outputString);
        self::assertStringContainsString('You must now add "extension=example_pie_extension" to your php.ini', $outputString);

        if (
            ! preg_match('#^Install complete: (.*)$#m', $outputString, $matches)
            || ! array_key_exists(1, $matches)
            || $matches[1] === ''
            || ! file_exists($matches[1])
            || ! is_file($matches[1])
        ) {
            return;
        }

        $fileToRemove = $matches[1];
        assert(is_string($fileToRemove));
        $rmCommand = ['rm', $fileToRemove];
        if (! is_writable($fileToRemove)) {
            array_unshift($rmCommand, 'sudo');
        }

        (new Process($rmCommand))->mustRun();
    }

    #[RequiresOperatingSystemFamily('Windows')]
    public function testInstallCommandWillInstallCompatibleExtensionWindows(): void
    {
        $this->commandTester->execute(['requested-package-and-version' => self::TEST_PACKAGE]);

        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();
        self::assertStringContainsString('Copied DLL to: ', $outputString);
        self::assertStringContainsString('You must now add "extension=example_pie_extension" to your php.ini', $outputString);

        if (
            ! preg_match('#^Copied DLL to: (.*)$#m', $outputString, $matches)
            || ! array_key_exists(1, $matches)
            || $matches[1] === ''
            || ! file_exists($matches[1])
            || ! is_file($matches[1])
        ) {
            return;
        }

        (new Process(['rm', $matches[1]]))->mustRun();
    }
}
