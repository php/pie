<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Command;

use Php\Pie\Command\DownloadCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

use const PHP_VERSION;
use const PHP_VERSION_ID;

#[CoversClass(DownloadCommand::class)]
class DownloadCommandTest extends TestCase
{
    public function testDownloadCommand(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects(self::once())
            ->method('getArgument')
            ->with('requested-package-and-version')
            ->willReturn('ramsey/uuid');

        $output = new BufferedOutput();

        $command = new DownloadCommand();
        self::assertSame(0, $command->execute($input, $output));

        $outputString = $output->fetch();
        self::assertStringContainsString('Found package: ramsey/uuid (version: ', $outputString);
        self::assertStringContainsString('Dist download URL: https://api.github.com/repos/ramsey/uuid/zipball/', $outputString);
    }

    public function testDownloadCommandFailsWhenUsingIncompatiblePhpVersion(): void
    {
        if (PHP_VERSION_ID >= 80200) {
            self::markTestSkipped('This test can only run on older than PHP 8.2 - you are running ' . PHP_VERSION);
        }

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::once())
            ->method('getArgument')
            ->with('requested-package-and-version')
            ->willReturn('phpunit/phpunit:^11.0');

        $output = new BufferedOutput();

        $command = new DownloadCommand();

        // @todo narrow this down to our true expected failure;
        //       i.e. phpunit/phpunit:^11.0 should NOT be installable on PHP 8.1
        $this->expectException(Throwable::class);
        $command->execute($input, $output);
    }
}
