<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Command;

use Php\Pie\Command\DownloadCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(DownloadCommand::class)]
class DownloadCommandTest extends TestCase
{
    public function testDownloadCommand(): void
    {
        $input  = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $output->expects(self::once())
            ->method('writeln')
            ->with('<info>to do</info>');

        $command = new DownloadCommand();
        self::assertSame(0, $command->execute($input, $output));
    }
}
