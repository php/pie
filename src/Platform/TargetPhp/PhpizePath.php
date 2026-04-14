<?php

declare(strict_types=1);

namespace Php\Pie\Platform\TargetPhp;

use RuntimeException;
use Symfony\Component\Process\Process;

use function assert;
use function file_exists;
use function is_executable;
use function Safe\preg_match;
use function Safe\preg_replace;
use function trim;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
final class PhpizePath
{
    /** @param non-empty-string $phpizeBinaryPath */
    public function __construct(public readonly string $phpizeBinaryPath)
    {
    }

    public static function looksLikeValidPhpize(string $phpizePathToCheck, string|null $forPhpApiVersion = null): bool
    {
        $phpizeAttempt = $phpizePathToCheck; // @todo
        if ($phpizeAttempt === '') {
            return false;
        }

        if (! file_exists($phpizeAttempt) || ! is_executable($phpizeAttempt)) {
            return false;
        }

        $phpizeProcess = new Process([$phpizeAttempt, '--version']);
        if ($phpizeProcess->run() !== 0) {
            return false;
        }

        if (
            ! preg_match('/PHP Api Version:\s*(.*)/', $phpizeProcess->getOutput(), $m)
            || $m[1] === ''
        ) {
            return false;
        }

        return $forPhpApiVersion === null || $forPhpApiVersion === $m[1];
    }

    public static function guessFrom(PhpBinaryPath $phpBinaryPath): self
    {
        $expectedApiVersion = $phpBinaryPath->phpApiVersion();

        $phpizeAttempts = [];

        // Try to add `phpize` from path
        $whichPhpize = new Process(['which', 'phpize']);
        if ($whichPhpize->run() === 0) {
            $phpizeAttempts[] = trim($whichPhpize->getOutput());
        }

        // Try to guess based on the `php` path itself
        $phpizeAttempts[] = preg_replace('((.*)php)', '$1phpize', $phpBinaryPath->phpBinaryPath);

        foreach ($phpizeAttempts as $phpizeAttempt) {
            assert($phpizeAttempt !== '');

            if (self::looksLikeValidPhpize($phpizeAttempt, $expectedApiVersion)) {
                return new self($phpizeAttempt);
            }
        }

        throw new RuntimeException('Could not find a suitable `phpize` binary, you may provide one using the "--with-phpize-path" option.');
    }
}
