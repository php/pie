<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Webmozart\Assert\Assert;

use function file_exists;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class Path
{
    /**
     * Static helper to generate a vaguely random temporary path. This is not intended to be cryptographically secure,
     * nor do we need to support high concurrency or strong randomness.
     *
     * @return non-empty-string
     */
    public static function vaguelyRandomTempPath(): string
    {
        $localTempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_downloader_', true);
        if (! file_exists($localTempPath)) {
            mkdir($localTempPath, recursive: true);
        }

        Assert::stringNotEmpty($localTempPath);
        Assert::directory($localTempPath);

        return $localTempPath;
    }
}
