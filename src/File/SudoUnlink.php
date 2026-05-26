<?php

declare(strict_types=1);

namespace Php\Pie\File;

use Composer\Util\Platform;
use Php\Pie\Util\Process;
use Safe\Exceptions\FilesystemException;
use Symfony\Component\Process\Exception\ProcessFailedException;

use function file_exists;
use function is_writable;
use function Safe\unlink;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class SudoUnlink
{
    public static function singleFile(string $filename): void
    {
        if (! file_exists($filename)) {
            return;
        }

        if (! Platform::isWindows() && is_writable($filename)) {
            try {
                unlink($filename);
            } catch (FilesystemException $e) {
                throw FailedToUnlinkFile::fromUnlinkError($filename, $e);
            }

            return;
        }

        if (Platform::isWindows()) {
            WindowsDelete::usingMoveToTemp($filename);

            return;
        }

        if (! Sudo::exists()) {
            throw FailedToUnlinkFile::fromNoPermissions($filename);
        }

        try {
            Process::run([Sudo::find(), 'rm', '--', $filename], timeout: Process::SHORT_TIMEOUT);
        } catch (ProcessFailedException $processFailedException) {
            throw FailedToUnlinkFile::fromSudoRmProcessFailed($filename, $processFailedException);
        }

        if (! file_exists($filename)) {
            return;
        }

        throw FailedToUnlinkFile::fromNoPermissions($filename);
    }
}
