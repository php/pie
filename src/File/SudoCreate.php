<?php

declare(strict_types=1);

namespace Php\Pie\File;

use Php\Pie\Util\CaptureErrors;
use Php\Pie\Util\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use function dirname;
use function file_exists;
use function is_writable;
use function touch;

final class SudoCreate
{
    public static function file(string $filename): void
    {
        /**
         * Note: strictly speaking, `touch` is a command to:
         *
         * > Update the access and modification times of each FILE to the current time.
         *
         * But, the way we are using `touch` is to just create the file, hence
         * the class naming is to reflect that. So; if the file already exists
         * we can exit early (as we're not actually interested in updating the
         * access/modification times of the file currently).
         */
        if (file_exists($filename)) {
            return;
        }

        if (is_writable(dirname($filename))) {
            $capturedErrors  = [];
            $touchSuccessful = CaptureErrors::for(
                static fn () => touch($filename),
                $capturedErrors,
            );

            if (! $touchSuccessful) {
                throw FailedToCreateFile::fromTouchErrors($filename, $capturedErrors);
            }

            return;
        }

        if (! Sudo::exists()) {
            throw FailedToCreateFile::fromNoPermissions($filename);
        }

        try {
            Process::run([Sudo::find(), 'touch', $filename]);
        } catch (ProcessFailedException $processFailedException) {
            throw FailedToCreateFile::fromSudoTouchProcessFailed($filename, $processFailedException);
        }
    }
}
