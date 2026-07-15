<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use Php\Pie\Command\SearchCommand;
use Php\Pie\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchCommand::class)]
final class SearchCommandTest extends TestCase
{
    private CommandTester $commandTester;

    public function setUp(): void
    {
        $this->commandTester = new CommandTester(Container::testFactory()->get(SearchCommand::class));
    }

    public function testSearchFindsMatchingPackages(): void
    {
        $this->commandTester->execute(['search-term' => ['example-pie-extension']]);

        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();
        self::assertStringContainsString('asgrim/example-pie-extension', $outputString);
        self::assertStringContainsString('Example PIE extension', $outputString);
        self::assertStringContainsString('provides extension: example_pie_extension', $outputString);
    }

    public function testSearchWithNoMatchesReturnsSuccessWithMessage(): void
    {
        $this->commandTester->execute(['search-term' => ['this-is-an-extension-all-about-happy-things-but-does-not-really-exist']]);

        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();
        self::assertStringContainsString('No packages found matching "this-is-an-extension-all-about-happy-things-but-does-not-really-exist".', $outputString);
    }
}
