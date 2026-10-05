<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

use Php\Pie\Util\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use function array_key_exists;
use function getenv;
use function serialize;

/** @internal This is not public API for PIE. */
class PkgConfig
{
    /** @var array<string, string|null> */
    private array $results = [];

    public function __construct(private readonly string $executable = 'pkg-config')
    {
    }

    public function provides(string $library): string|null
    {
        // Include the environment so changes to PATH and PKG_CONFIG_* are respected.
        $key = serialize([$library, getenv()]);
        if (array_key_exists($key, $this->results)) {
            return $this->results[$key];
        }

        try {
            return $this->results[$key] = Process::run([$this->executable, '--print-provides', '--print-errors', $library]);
        } catch (ProcessFailedException) {
            return $this->results[$key] = null;
        }
    }

    public function clear(): void
    {
        $this->results = [];
    }
}
