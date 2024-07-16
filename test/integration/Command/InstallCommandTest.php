<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use Php\Pie\Command\InstallCommand;
use Php\Pie\Container;
use Php\Pie\DependencyResolver\UnableToResolveRequirement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function array_combine;
use function array_map;
use function file_exists;
use function is_executable;

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
        self::assertStringContainsString('Install complete.', $outputString);
        self::assertStringContainsString('You must now add "extension=example_pie_extension.so" to your php.ini', $outputString);
    }
}
