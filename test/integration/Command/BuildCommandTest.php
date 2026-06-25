<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use Composer\Util\Platform;
use Php\Pie\Command\BuildCommand;
use Php\Pie\Container;
use PHPUnit\Framework\Attributes\CoversClass;

use function str_contains;

#[CoversClass(BuildCommand::class)]
class BuildCommandTest extends IsolatedWorkingDirectoryTestCase
{
    private const TEST_PACKAGE = 'asgrim/example-pie-extension';

    private CommandTester $commandTester;

    public function setUp(): void
    {
        parent::setUp();

        $this->commandTester = new CommandTester(Container::testFactory()->get(BuildCommand::class));
    }

    public function testBuildCommandWillBuildTheExtension(): void
    {
        $this->commandTester->execute(['requested-package-and-version' => [self::TEST_PACKAGE]]);

        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();

        if (Platform::isWindows()) {
            self::assertStringContainsString('Found prebuilt archive', $outputString);

            return;
        }

        if (str_contains($outputString, 'Found prebuilt archive')) {
            self::assertStringContainsString('Found prebuilt archive', $outputString);
            self::assertStringContainsString('Pre-packaged binary found', $outputString);

            return;
        }

        self::assertStringContainsString('phpize complete.', $outputString);
        self::assertStringContainsString('Configure complete', $outputString);
        self::assertStringContainsString('Build complete:', $outputString);
    }
}
