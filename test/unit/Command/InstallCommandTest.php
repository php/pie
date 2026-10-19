<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Command;

use Composer\IO\BufferIO;
use Php\Pie\Command\InstallCommand;
use Php\Pie\Command\InvokeSubCommand;
use Php\Pie\ComposerIntegration\ComposerIntegrationHandler;
use Php\Pie\Container;
use Php\Pie\DependencyResolver\DependencyInstaller\PrescanSystemDependencies;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\Installing\InstallForPhpProject\FindMatchingPackages;
use Php\Pie\SelfManage\BuildTools\CheckAllBuildTools;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(InstallCommand::class)]
final class InstallCommandTest extends TestCase
{
    public function testFromLockOptionExistsOnInstallCommand(): void
    {
        $command    = Container::testFactory()->get(InstallCommand::class);
        $definition = $command->getDefinition();

        self::assertTrue($definition->hasOption(InstallCommand::OPTION_FROM_LOCK));
    }

    public function testNoDevWithFromLockWarnsThatNoDevHasNoEffect(): void
    {
        $io = new BufferIO();

        $composerIntegrationHandler = $this->createMock(ComposerIntegrationHandler::class);
        $composerIntegrationHandler->expects(self::once())->method('runInstall');

        $commandTester = new CommandTester(new InstallCommand(
            Container::testFactory(),
            $this->createMock(DependencyResolver::class),
            $this->createMock(PrescanSystemDependencies::class),
            $composerIntegrationHandler,
            $this->createMock(InvokeSubCommand::class),
            $this->createMock(FindMatchingPackages::class),
            $io,
            $this->createMock(CheckAllBuildTools::class),
        ));

        $commandTester->execute([
            '--from-lock' => true,
            '--no-dev' => true,
        ]);

        $commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString(
            'The --no-dev option has no effect when used with --from-lock',
            $io->getOutput(),
        );
    }
}
