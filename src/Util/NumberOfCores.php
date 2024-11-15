<?php

declare(strict_types=1);

namespace Php\Pie\Util;

use Symfony\Component\Process\Process;

use function is_numeric;
use function trim;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class NumberOfCores
{
    /** @psalm-suppress UnusedConstructor */
    private function __construct()
    {
    }

    /**
     * @param list<string> $params
     *
     * @return positive-int
     */
    private static function tryCommand(string $command, array $params): int|null
    {
        $whichProcess = new Process(['which', $command]);
        if ($whichProcess->run() === 0) {
            $commandPath = trim($whichProcess->getOutput());

            $commandProcess = new Process([$commandPath, ...$params]);
            if ($commandProcess->run() === 0) {
                $commandResult = trim($commandProcess->getOutput());
                if (is_numeric($commandResult)) {
                    $commandNumericResult = (int) $commandResult;

                    if ($commandNumericResult > 0) {
                        return $commandNumericResult;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Try a few different ways of determining number of CPUs. If we can't guess
     * how many CPUs someone has, then default to 1 for safety.
     *
     * @return positive-int
     */
    public static function determine(): int
    {
        $nproc = self::tryCommand('nproc', ['--all']);
        if ($nproc !== null) {
            return $nproc;
        }

        $getconf = self::tryCommand('getconf', ['_NPROCESSORS_ONLN']);
        if ($getconf !== null) {
            return $getconf;
        }

        $sysctl = self::tryCommand('sysctl', ['-n', 'hw.ncpu']);
        if ($sysctl !== null) {
            return $sysctl;
        }

        // Default to 1 for safety
        return 1;
    }
}
