<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use Composer\Util\Platform;
use Php\Pie\Command\BuildCommand;
use Php\Pie\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BuildCommand::class)]
class BuildCommandTest extends TestCase
{
    private const TEST_PACKAGE = 'asgrim/example-pie-extension';

    private CommandTester $commandTester;

    public function setUp(): void
    {
        $this->commandTester = new CommandTester(Container::testFactory()->get(BuildCommand::class));
    }

    public function testBuildCommandWillBuildTheExtension(): void
    {
        $this->commandTester->execute(['requested-package-and-version' => self::TEST_PACKAGE]);

        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();

        if (Platform::isWindows()) {
            self::assertStringContainsString('Nothing to do on Windows', $outputString);

            return;
        }

        self::assertStringContainsString('phpize complete.', $outputString);
        self::assertStringContainsString('Configure complete', $outputString);
        self::assertStringContainsString('Build complete:', $outputString);
    }
}
