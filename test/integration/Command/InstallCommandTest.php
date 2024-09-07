<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use Php\Pie\Command\InstallCommand;
use Php\Pie\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

use function array_key_exists;
use function array_unshift;
use function assert;
use function file_exists;
use function is_file;
use function is_string;
use function is_writable;
use function preg_match;

use const PHP_VERSION;
use const PHP_VERSION_ID;

#[CoversClass(InstallCommand::class)]
class InstallCommandTest extends TestCase
{
    private const TEST_PACKAGE = 'asgrim/example-pie-extension';

    private CommandTester $commandTester;

    public function setUp(): void
    {
        $this->commandTester = new CommandTester(Container::factory()->get(InstallCommand::class));
    }

    public function testInstallCommandWillInstallCompatibleExtension(): void
    {
        if (PHP_VERSION_ID < 80300 || PHP_VERSION_ID >= 80400) {
            self::markTestSkipped('This test can only run on PHP 8.3 - you are running ' . PHP_VERSION);
        }

        $this->commandTester->execute(['requested-package-and-version' => self::TEST_PACKAGE]);

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
}
