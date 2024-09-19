<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use Php\Pie\Command\ShowCommand;
use Php\Pie\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function get_loaded_extensions;
use function phpversion;

#[CoversClass(ShowCommand::class)]
final class ShowCommandTest extends TestCase
{
    private CommandTester $commandTester;

    public function setUp(): void
    {
        $this->commandTester = new CommandTester(Container::factory()->get(ShowCommand::class));
    }

    public function testExecute(): void
    {
        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();

        foreach (get_loaded_extensions() as $extension) {
            self::assertStringContainsString(
                $extension . ':' . (phpversion($extension) === false ? '0' : phpversion($extension)),
                $outputString,
            );
        }
    }
}
