<?php

declare(strict_types=1);

namespace Php\Pie\Platform\TargetPhp;

use RuntimeException;
use Symfony\Component\Process\Process;

use function array_key_exists;
use function assert;
use function file_exists;
use function is_executable;
use function preg_match;
use function preg_replace;
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
            if (! file_exists($phpizeAttempt) || ! is_executable($phpizeAttempt)) {
                continue;
            }

            $phpizeProcess = new Process([$phpizeAttempt, '--version']);
            if ($phpizeProcess->run() !== 0) {
                continue;
            }

            if (
                ! preg_match('/PHP Api Version:\s*(.*)/', $phpizeProcess->getOutput(), $m)
                || ! array_key_exists(1, $m)
                || $m[1] === ''
            ) {
                continue;
            }

            if ($expectedApiVersion === $m[1]) {
                return new self($phpizeAttempt);
            }
        }

        throw new RuntimeException('Could not find a suitable `phpize` binary, you may provide one using the "--with-phpize-path" option.');
    }
}
