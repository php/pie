<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration;

use Composer\IO\IOInterface;
use Php\Pie\ComposerIntegration\MinimalHelperSet;
use Php\Pie\ComposerIntegration\QuieterConsoleIO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(QuieterConsoleIO::class)]
final class QuieterConsoleIOTest extends TestCase
{
    public function testErrorsAreLoggedAndWrittenWhenVerbose(): void
    {
        $symfonyInput  = $this->createMock(InputInterface::class);
        $symfonyOutput = $this->createMock(OutputInterface::class);
        $symfonyOutput
            ->method('getVerbosity')
            ->willReturn(OutputInterface::VERBOSITY_VERBOSE);
        $symfonyOutput
            ->expects(self::once())
            ->method('write')
            ->with('Oh no');

        $io = new QuieterConsoleIO($symfonyInput, $symfonyOutput, $this->createMock(MinimalHelperSet::class));
        $io->writeError('Oh no');

        self::assertSame(['Oh no'], $io->errors);
    }

    public function testArrayOfErrorsAreLoggedAndWrittenWhenVerbose(): void
    {
        $symfonyInput  = $this->createMock(InputInterface::class);
        $symfonyOutput = $this->createMock(OutputInterface::class);
        $symfonyOutput
            ->method('getVerbosity')
            ->willReturn(OutputInterface::VERBOSITY_VERBOSE);
        $symfonyOutput
            ->expects(self::once())
            ->method('write')
            ->with(['Oh no', 'Bad things']);

        $io = new QuieterConsoleIO($symfonyInput, $symfonyOutput, $this->createMock(MinimalHelperSet::class));
        $io->writeError(['Oh no', 'Bad things']);

        self::assertSame(['Oh no', 'Bad things'], $io->errors);
    }

    public function testErrorsAreLoggedButNotWritten(): void
    {
        $symfonyInput  = $this->createMock(InputInterface::class);
        $symfonyOutput = $this->createMock(OutputInterface::class);
        $symfonyOutput
            ->method('getVerbosity')
            ->willReturn(OutputInterface::VERBOSITY_NORMAL);
        $symfonyOutput
            ->expects(self::never())
            ->method('write');

        $io = new QuieterConsoleIO($symfonyInput, $symfonyOutput, $this->createMock(MinimalHelperSet::class));
        $io->writeError('Oh no');

        self::assertSame(['Oh no'], $io->errors);
    }

    /** @return array<string, array{0: OutputInterface::VERBOSITY_*, 1: list<non-empty-string>}> */
    public static function verbosyExpectationsProvider(): array
    {
        return [
            'q' => [OutputInterface::VERBOSITY_QUIET, []],
            'normal' => [OutputInterface::VERBOSITY_NORMAL, ['Quiet']],
            'v' => [OutputInterface::VERBOSITY_VERBOSE, ['Quiet', 'Normal']],
            'vv' => [OutputInterface::VERBOSITY_VERY_VERBOSE, ['Quiet', 'Normal', 'Verbose']],
            'vvv' => [OutputInterface::VERBOSITY_DEBUG, ['Quiet', 'Normal', 'Verbose', 'VeryVerbose', 'Debug']],
        ];
    }

    /**
     * @param OutputInterface::VERBOSITY_* $symfonyVerbosity
     * @param list<non-empty-string>       $expectedMessages
     */
    #[DataProvider('verbosyExpectationsProvider')]
    public function testComposerVerbosityIsMapped(int $symfonyVerbosity, array $expectedMessages): void
    {
        $symfonyInput  = $this->createMock(InputInterface::class);
        $symfonyOutput = new class extends Output {
            /** @var list<string> */
            public array $loggedMessages = [];

            protected function doWrite(string $message, bool $newline): void
            {
                $this->loggedMessages[] = $message;
            }
        };
        $symfonyOutput->setVerbosity($symfonyVerbosity);

        $io = new QuieterConsoleIO($symfonyInput, $symfonyOutput, $this->createMock(MinimalHelperSet::class));

        $io->write('Quiet', verbosity: IOInterface::QUIET);
        $io->write('Normal', verbosity: IOInterface::NORMAL);
        $io->write('Verbose', verbosity: IOInterface::VERBOSE);
        $io->write('VeryVerbose', verbosity: IOInterface::VERY_VERBOSE);
        $io->write('Debug', verbosity: IOInterface::DEBUG);

        self::assertSame($expectedMessages, $symfonyOutput->loggedMessages);
    }
}
