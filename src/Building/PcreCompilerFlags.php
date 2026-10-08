<?php

declare(strict_types=1);

namespace Php\Pie\Building;

use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Util\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class PcreCompilerFlags
{
    private function __construct()
    {
    }

    /** @return non-empty-string|null */
    public static function forTargetPlatform(TargetPlatform $targetPlatform): string|null
    {
        if (! $targetPlatform->phpBinaryPath->usesExternalPcre()) {
            return null;
        }

        try {
            $flags = Process::run(['pkg-config', '--cflags', 'libpcre2-8']);
        } catch (ProcessFailedException) {
            return null;
        }

        return $flags !== '' ? $flags : null;
    }
}
