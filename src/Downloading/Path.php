<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Webmozart\Assert\Assert;

use function file_exists;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

final class Path
{
    /** @return non-empty-string */
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
