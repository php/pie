<?php

declare(strict_types=1);

namespace Php\Pie\File;

use RuntimeException;

use function array_key_exists;
use function is_string;
use function preg_match;
use function realpath;

use const DIRECTORY_SEPARATOR;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class FullPathToSelf
{
    public function __construct(private readonly string $originalCwd)
    {
    }

    /** @return non-empty-string */
    public function __invoke(): string
    {
        $phpSelf = array_key_exists('PHP_SELF', $_SERVER) && is_string($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
        if ($phpSelf === '') {
            throw new RuntimeException('Could not find PHP_SELF');
        }

        return $this->isAbsolutePath($phpSelf)
            ? $phpSelf
            : ($this->originalCwd . DIRECTORY_SEPARATOR . $phpSelf);
    }

    private function isAbsolutePath(string $path): bool
    {
        if (realpath($path) === $path) {
            return true;
        }

        if ($path === '' || $path === '.') {
            return false;
        }

        if (preg_match('#^[a-zA-Z]:\\\\#', $path)) {
            return true;
        }

        return $path[0] === '/' || $path[0] === '\\';
    }
}
