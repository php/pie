<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use Composer\Util\Platform;
use Php\Pie\Command\CheckBuildToolsCommand;
use Php\Pie\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;

#[CoversClass(CheckBuildToolsCommand::class)]
final class CheckBuildToolsCommandTest extends IsolatedWorkingDirectoryTestCase
{
    private CommandTester $commandTester;

    public function setUp(): void
    {
        parent::setUp();

        $this->commandTester = new CommandTester(Container::testFactory()->get(CheckBuildToolsCommand::class));
    }

    public function testCheckBuildToolsCommandListsRequiredToolsAndSucceeds(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('This test can only run on non-Windows systems');
        }

        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();
        self::assertStringContainsString('Build tools typically required to build extensions:', $outputString);
        self::assertStringContainsString('gcc', $outputString);
        self::assertStringContainsString('make', $outputString);
        self::assertStringContainsString('phpize', $outputString);
        self::assertStringContainsString('All build tools are installed.', $outputString);
    }

    #[RequiresOperatingSystemFamily('Windows')]
    public function testCheckBuildToolsCommandSkipsCheckOnWindows(): void
    {
        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();
        self::assertStringContainsString('Build tools are not required on Windows systems!', $outputString);
    }
}
