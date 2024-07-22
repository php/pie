<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use Composer\Util\Platform;
use Php\Pie\Command\BuildCommand;
use Php\Pie\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use const PHP_VERSION;
use const PHP_VERSION_ID;

#[CoversClass(BuildCommand::class)]
class BuildCommandTest extends TestCase
{
    private const TEST_PACKAGE = 'asgrim/example-pie-extension';

    private CommandTester $commandTester;

    public function setUp(): void
    {
        $this->commandTester = new CommandTester(Container::factory()->get(BuildCommand::class));
    }

    public function testBuildCommandWillBuildTheExtension(): void
    {
        if (PHP_VERSION_ID < 80300 || PHP_VERSION_ID >= 80400) {
            self::markTestSkipped('This test can only run on PHP 8.3 - you are running ' . PHP_VERSION);
        }

        $this->commandTester->execute(['requested-package-and-version' => self::TEST_PACKAGE]);

        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();

        self::assertStringContainsString('Found package: asgrim/example-pie-extension:1.0.1 which provides ext-example_pie_extension', $outputString);

        if (Platform::isWindows()) {
            self::assertStringContainsString('Nothing to do on Windows.', $outputString);

            return;
        }

        self::assertStringContainsString('phpize complete.', $outputString);
        self::assertStringContainsString('Configure complete.', $outputString);
        self::assertStringContainsString('Build complete:', $outputString);
    }
}
