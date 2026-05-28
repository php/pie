<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use Composer\Util\Platform;
use Php\Pie\Command\InstallCommand;
use Php\Pie\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

use function array_combine;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_unshift;
use function file_exists;
use function is_executable;
use function is_writable;
use function Safe\preg_match;

#[CoversClass(InstallCommand::class)]
class InstallCommandTest extends IsolatedWorkingDirectoryTestCase
{
    private const TEST_PACKAGE = 'asgrim/example-pie-extension';

    private CommandTester $commandTester;
    private string|null $lastInstalledBinary = null;

    public function setUp(): void
    {
        parent::setUp();

        $this->commandTester = new CommandTester(Container::testFactory()->get(InstallCommand::class));
    }

    protected function tearDown(): void
    {
        if ($this->lastInstalledBinary !== null && file_exists($this->lastInstalledBinary)) {
            $rmCommand = ['rm', $this->lastInstalledBinary];
            if (! is_writable($this->lastInstalledBinary)) {
                array_unshift($rmCommand, 'sudo');
            }

            (new Process($rmCommand))->run();
        }

        parent::tearDown();
    }

    /** @return array<string, array{0: string}> */
    public static function phpPathProvider(): array
    {
        // data providers cannot return empty, even if the test is skipped
        if (Platform::isWindows()) {
            return ['skip' => ['skip']];
        }

        $possiblePhpConfigPaths = array_filter(
            [
                '/usr/bin/php-config',
                '/usr/bin/php-config8.5',
                '/usr/bin/php-config8.4',
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
            [
                'requested-package-and-version' => self::TEST_PACKAGE,
                '--with-php-config' => $phpConfigPath,
                '--skip-enable-extension' => true,
            ],
            ['verbosity' => BufferedOutput::VERBOSITY_VERY_VERBOSE],
        );

        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();

        if (
            preg_match('#^Install complete: (.*)$#m', $outputString, $matches)
            && array_key_exists(1, $matches)
            && $matches[1] !== ''
        ) {
            $this->lastInstalledBinary = $matches[1];
        }

        self::assertStringContainsString('Install complete: ', $outputString);
        self::assertStringContainsString('You must now add "extension=example_pie_extension" to your php.ini', $outputString);
    }

    #[RequiresOperatingSystemFamily('Windows')]
    public function testInstallCommandWillInstallCompatibleExtensionWindows(): void
    {
        $this->commandTester->execute([
            'requested-package-and-version' => self::TEST_PACKAGE,
            '--skip-enable-extension' => true,
        ]);

        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();

        if (
            preg_match('#^Copied DLL to: (.*)$#m', $outputString, $matches)
            && array_key_exists(1, $matches)
            && $matches[1] !== ''
        ) {
            $this->lastInstalledBinary = $matches[1];
        }

        self::assertStringContainsString('Copied DLL to: ', $outputString);
        self::assertStringContainsString('You must now add "extension=example_pie_extension" to your php.ini', $outputString);
    }
}
