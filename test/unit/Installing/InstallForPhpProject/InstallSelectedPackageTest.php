<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\InstallForPhpProject;

use Php\Pie\Command\InvokeSubCommand;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\Installing\InstallForPhpProject\InstallSelectedPackage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

#[CoversClass(InstallSelectedPackage::class)]
final class InstallSelectedPackageTest extends TestCase
{
    public function testSubCommandIsInvoked(): void
    {
        $command = $this->createMock(Command::class);
        $input   = $this->createMock(InputInterface::class);
        $invoker = $this->createMock(InvokeSubCommand::class);
        $invoker->expects(self::once())
            ->method('__invoke')
            ->with(
                $command,
                [
                    'command' => 'install',
                    'requested-package-and-version' => ['foo/foo:^1.0'],
                ],
                $input,
            )
            ->willReturn(0);

        $installer = new InstallSelectedPackage($invoker);
        $installer->withSubCommand(
            [
                new RequestedPackageAndVersion(
                    'foo/foo',
                    '^1.0',
                ),
            ],
            $command,
            $input,
        );
    }
}
