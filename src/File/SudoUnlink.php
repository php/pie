<?php

declare(strict_types=1);

namespace Php\Pie\File;

use Php\Pie\Util\CaptureErrors;
use Php\Pie\Util\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use function file_exists;
use function is_writable;
use function unlink;

final class SudoUnlink
{
    public static function singleFile(string $filename): void
    {
        if (! file_exists($filename)) {
            return;
        }

        if (is_writable($filename)) {
            $capturedErrors   = [];
            $unlinkSuccessful = CaptureErrors::for(
                static fn () => unlink($filename),
                $capturedErrors,
            );

            if (! $unlinkSuccessful || file_exists($filename)) {
                throw FailedToUnlinkFile::fromUnlinkErrors($filename, $capturedErrors);
            }

            return;
        }

        if (! Sudo::exists()) {
            throw FailedToUnlinkFile::fromNoPermissions($filename);
        }

        try {
            Process::run([Sudo::find(), 'rm', $filename]);
        } catch (ProcessFailedException $processFailedException) {
            throw FailedToUnlinkFile::fromSudoRmProcessFailed($filename, $processFailedException);
        }

        if (! file_exists($filename)) {
            return;
        }

        FailedToUnlinkFile::fromNoPermissions($filename);
    }
}
