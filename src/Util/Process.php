<?php

declare(strict_types=1);

namespace Php\Pie\Util;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process as SymfonyProcess;

use function trim;

final class Process
{
    /** @psalm-suppress UnusedConstructor */
    private function __construct()
    {
    }

    /**
     * Just a helper to invoke a Symfony Process command with a simplified API
     * for the common invocations we have in PIE.
     *
     * Things to note:
     *  - uses mustRun (i.e. throws exception if command execution fails)
     *  - very short timeout by default (5 seconds)
     *  - output is trimmed
     *
     * @param list<string>                                                         $command
     * @param callable(SymfonyProcess::ERR|SymfonyProcess::OUT, string): void|null $outputCallback
     *
     * @throws ProcessFailedException
     */
    public static function run(
        array $command,
        string|null $workingDirectory = null,
        int|null $timeout = 5,
        callable|null $outputCallback = null,
    ): string {
        return trim((new SymfonyProcess($command, $workingDirectory, timeout: $timeout))
            ->mustRun($outputCallback)
            ->getOutput());
    }
}
