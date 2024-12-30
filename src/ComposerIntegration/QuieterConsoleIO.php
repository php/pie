<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Closure;
use Composer\IO\ConsoleIO;
use Composer\IO\IOInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_merge;
use function assert;
use function is_array;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class QuieterConsoleIO extends ConsoleIO
{
    /** @var string[] */
    public array $errors = [];

    public function __construct(InputInterface $input, OutputInterface $output, MinimalHelperSet $helperSet)
    {
        parent::__construct($input, $output, $helperSet);

        /**
         * Shifts Composer's normal output level by one, e.g. what Composer
         * normally outputs, only output with `-v`
         */
        $this->overrideVerbosityMap([
            self::QUIET => OutputInterface::VERBOSITY_NORMAL,
            self::NORMAL => OutputInterface::VERBOSITY_VERBOSE,
            self::VERBOSE => OutputInterface::VERBOSITY_VERY_VERBOSE,
            self::VERY_VERBOSE => OutputInterface::VERBOSITY_DEBUG,
            self::DEBUG => OutputInterface::VERBOSITY_DEBUG,
        ]);
    }

    /** @param array<IOInterface::*, OutputInterface::VERBOSITY_*> $newVerbosityMap */
    private function overrideVerbosityMap(array $newVerbosityMap): void
    {
        $overrideFunction = Closure::bind(
            function (ConsoleIO $consoleIO) use ($newVerbosityMap): void {
                /** @psalm-suppress InaccessibleProperty */
                $consoleIO->verbosityMap = $newVerbosityMap;
            },
            null,
            ConsoleIO::class,
        );
        assert($overrideFunction !== null);
        $overrideFunction($this);
    }

    /** {@inheritDoc} */
    public function writeError($messages, bool $newline = true, int $verbosity = self::NORMAL): void
    {
        if ($verbosity <= self::NORMAL) {
            if (is_array($messages)) {
                $this->errors = array_merge($this->errors, $messages);

                parent::writeError($messages, $newline, $verbosity);

                return;
            }

            $this->errors[] = $messages;
        }

        parent::writeError($messages, $newline, $verbosity);
    }
}
