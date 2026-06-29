<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Command;

use Php\Pie\Command\InstallCommand;
use Php\Pie\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InstallCommand::class)]
final class InstallCommandTest extends TestCase
{
    public function testFromLockOptionExistsOnInstallCommand(): void
    {
        $command    = Container::testFactory()->get(InstallCommand::class);
        $definition = $command->getDefinition();

        self::assertTrue($definition->hasOption(InstallCommand::OPTION_FROM_LOCK));
    }
}
