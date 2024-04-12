<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use Php\Pie\Command\DownloadCommand;
use Php\Pie\Container;
use Php\Pie\DependencyResolver\UnableToResolveRequirement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use const PHP_VERSION;
use const PHP_VERSION_ID;

#[CoversClass(DownloadCommand::class)]
class DownloadCommandTest extends TestCase
{
    private CommandTester $commandTester;

    public function setUp(): void
    {
        $this->commandTester = new CommandTester(Container::factory()->get(DownloadCommand::class));
    }

    public function testDownloadCommand(): void
    {
        if (PHP_VERSION_ID < 80300 || PHP_VERSION_ID >= 80400) {
            self::markTestSkipped('This test can only run on PHP 8.3 - you are running ' . PHP_VERSION);
        }

        // 1.0.0 is only compatible with PHP 8.3.0
        $this->commandTester->execute(['requested-package-and-version' => 'asgrim/example-pie-extension:1.0.0']);

        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();
        self::assertStringContainsString('Found package: asgrim/example-pie-extension (version: 1.0.0)', $outputString);
        self::assertStringContainsString('Dist download URL: https://api.github.com/repos/asgrim/example-pie-extension/zipball/', $outputString);
        self::assertStringContainsString('Extracted asgrim/example-pie-extension:1.0.0 source', $outputString);
    }

    public function testDownloadCommandFailsWhenUsingIncompatiblePhpVersion(): void
    {
        if (PHP_VERSION_ID >= 80200) {
            self::markTestSkipped('This test can only run on older than PHP 8.2 - you are running ' . PHP_VERSION);
        }

        $this->expectException(UnableToResolveRequirement::class);
        // 1.0.0 is only compatible with PHP 8.3.0
        $this->commandTester->execute(['requested-package-and-version' => 'asgrim/example-pie-extension:1.0.0']);
    }
}
