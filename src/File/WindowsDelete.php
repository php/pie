<?php

declare(strict_types=1);

namespace Php\Pie\File;

use Php\Pie\Util\Process;
use RuntimeException;

use function file_exists;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class WindowsDelete
{
    /**
     * Windows will hold a lock on the DLL, so we can't actually replace or
     * delete the extension. However, Windows lets us move it out the way,
     * so move it to the temp directory. It can then be cleaned up later.
     */
    public static function usingMoveToTemp(string $filename): void
    {
        if (! file_exists($filename)) {
            return;
        }

        $newLockedExtFilename = tempnam(sys_get_temp_dir(), 'pie');
        if ($newLockedExtFilename === false) {
            throw new RuntimeException(sprintf(
                'Failed to create a temporary name for moving %s',
                $filename,
            ));
        }

        Process::run(['move', $filename, $newLockedExtFilename]);

        if (file_exists($filename)) {
            throw new RuntimeException(sprintf(
                'Failed to move %s to %s',
                $filename,
                $newLockedExtFilename,
            ));
        }
    }
}
