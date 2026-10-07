<?php

declare(strict_types=1);

namespace Php\Pie\Util;

use Composer\IO\IOInterface;
use Composer\Util\Platform;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process as SymfonyProcess;

use function array_sum;
use function arsort;
use function basename;
use function microtime;
use function register_shutdown_function;
use function Safe\fwrite;
use function sprintf;
use function str_contains;
use function strtolower;
use function trim;

use const STDERR;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class Process
{
    public const NO_TIMEOUT    = null;
    public const SHORT_TIMEOUT = 10;

    /** @var array<string, int> */
    private static array $invocationCounts = [];
    /** @var array<string, float> */
    private static array $invocationSeconds = [];

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
     * @param array<string, scalar>|null                                           $env
     *
     * @throws ProcessFailedException
     */
    public static function run(
        array $command,
        string|null $workingDirectory = null,
        int|null $timeout = self::NO_TIMEOUT,
        callable|null $outputCallback = null,
        array|null $env = null,
    ): string {
        $startedAt = microtime(true);

        try {
            return trim((new SymfonyProcess($command, $workingDirectory, $env, timeout: $timeout))
                ->mustRun($outputCallback)
                ->getOutput());
        } finally {
            self::recordInvocation($command, microtime(true) - $startedAt);
        }
    }

    /** @param list<string> $command */
    private static function recordInvocation(array $command, float $seconds): void
    {
        if (Platform::getEnv('PIE_DEBUG_PROCESS_COUNT') === false) {
            return;
        }

        if (self::$invocationCounts === []) {
            register_shutdown_function(static function (): void {
                arsort(self::$invocationSeconds);

                fwrite(STDERR, sprintf(
                    "Processes started: %d in %.2fs\n",
                    array_sum(self::$invocationCounts),
                    array_sum(self::$invocationSeconds),
                ));

                foreach (self::$invocationSeconds as $executable => $seconds) {
                    $count = self::$invocationCounts[$executable];

                    fwrite(STDERR, sprintf(
                        "  %s: %d in %.2fs (%.1fms avg)\n",
                        $executable,
                        $count,
                        $seconds,
                        $seconds / $count * 1000,
                    ));
                }
            });
        }

        $executable                           = basename($command[0] ?? '');
        self::$invocationCounts[$executable]  = (self::$invocationCounts[$executable] ?? 0) + 1;
        self::$invocationSeconds[$executable] = (self::$invocationSeconds[$executable] ?? 0.0) + $seconds;
    }

    /**
     * @param IOInterface::* $minVerbosity
     *
     * @return callable(SymfonyProcess::ERR|SymfonyProcess::OUT, string): void|null
     */
    public static function outputCallbackForVerbosity(IOInterface $io, int $minVerbosity): callable|null
    {
        if (
            ($minVerbosity === IOInterface::VERBOSE && ! $io->isVerbose() && ! $io->isVeryVerbose() && ! $io->isDebug())
            || ($minVerbosity === IOInterface::VERY_VERBOSE && ! $io->isVeryVerbose() && ! $io->isDebug())
            || ($minVerbosity === IOInterface::DEBUG && ! $io->isDebug())
        ) {
            return null;
        }

        return static function (string $type, string $outputMessage) use ($io): void {
            $io->write(sprintf(
                '%s%s%s',
                $type === SymfonyProcess::ERR ? '<comment>' : '',
                $outputMessage,
                $type === SymfonyProcess::ERR ? '</comment>' : '',
            ), false);
        };
    }

    public static function processProbablyPermissionDenied(ProcessFailedException $e): bool
    {
        $mergedProcessOutput = strtolower($e->getProcess()->getErrorOutput() . $e->getProcess()->getOutput());

        $needles = [
            'permission denied',
            'you must be root',
            'operation not permitted',
            'are you root',
            'has to be run with superuser privileges',
        ];

        foreach ($needles as $needle) {
            if (str_contains($mergedProcessOutput, $needle)) {
                return true;
            }
        }

        return false;
    }
}
