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
        $this->commandTester->execute(['requested-package-and-version' => 'ramsey/uuid']);

        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();
        self::assertStringContainsString('Found package: ramsey/uuid (version: ', $outputString);
        self::assertStringContainsString('Dist download URL: https://api.github.com/repos/ramsey/uuid/zipball/', $outputString);
    }

    public function testDownloadCommandFailsWhenUsingIncompatiblePhpVersion(): void
    {
        if (PHP_VERSION_ID >= 80200) {
            self::markTestSkipped('This test can only run on older than PHP 8.2 - you are running ' . PHP_VERSION);
        }

        $this->expectException(UnableToResolveRequirement::class);
        $this->commandTester->execute(['requested-package-and-version' => 'phpunit/phpunit:^11.0']);
    }
}
