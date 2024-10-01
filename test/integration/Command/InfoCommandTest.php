<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use Php\Pie\Command\InfoCommand;
use Php\Pie\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(InfoCommand::class)]
final class InfoCommandTest extends TestCase
{
    private CommandTester $commandTester;

    public function setUp(): void
    {
        $this->commandTester = new CommandTester(Container::factory()->get(InfoCommand::class));
    }

    public function testInfoCommandDisplaysInformation(): void
    {
        $this->commandTester->execute(['requested-package-and-version' => 'asgrim/example-pie-extension:dev-main#9b5e6c80a1e05556e4e6824f0c112a4992cee001']);

        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();
        self::assertStringContainsString('Extension name: example_pie_extension', $outputString);
        self::assertStringContainsString('Extension type: php-ext (PhpModule)', $outputString);
        self::assertStringContainsString('Composer package name: asgrim/example-pie-extension', $outputString);
        self::assertStringContainsString('Version: dev-main', $outputString);
        self::assertStringContainsString('Download URL: https://api.github.com/repos/asgrim/example-pie-extension/zipball/9b5e6c80a1e05556e4e6824f0c112a4992cee001', $outputString);
        self::assertStringContainsString('--enable-example-pie-extension  (whether to enable example-pie-extension support)', $outputString);
        self::assertStringContainsString('--with-hello-name=?  (Name ot use when saying hello)', $outputString);
    }
}
