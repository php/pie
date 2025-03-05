<?php

declare(strict_types=1);

namespace Php\Pie\File;

use Php\Pie\Util\CaptureErrors;
use Php\Pie\Util\Process;

use function dirname;
use function file_exists;
use function file_put_contents;
use function is_writable;
use function sys_get_temp_dir;
use function tempnam;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class SudoFilePut
{
    public static function contents(string $filename, string $content): void
    {
        $fileWritable = file_exists($filename) && is_writable($filename);
        $pathWritable = ! file_exists($filename) && file_exists(dirname($filename)) && is_writable(dirname($filename));

        if ($fileWritable || $pathWritable) {
            $capturedErrors  = [];
            $writeSuccessful = CaptureErrors::for(
                static fn () => file_put_contents($filename, $content),
                $capturedErrors,
            );

            if ($writeSuccessful === false) {
                throw FailedToWriteFile::fromFilePutContentErrors($filename, $capturedErrors);
            }

            return;
        }

        if (! Sudo::exists()) {
            throw FailedToWriteFile::fromNoPermissions($filename);
        }

        self::writeWithSudo($filename, $content);
    }

    private static function writeWithSudo(string $filename, string $content): void
    {
        $tempFilename = tempnam(sys_get_temp_dir(), 'pie_tmp_');
        if ($tempFilename === false) {
            throw FailedToWriteFile::fromNoPermissions($filename);
        }

        $capturedErrors  = [];
        $writeSuccessful = CaptureErrors::for(
            static fn () => file_put_contents($tempFilename, $content),
            $capturedErrors,
        );

        if ($writeSuccessful === false) {
            throw FailedToWriteFile::fromFilePutContentErrors($tempFilename, $capturedErrors);
        }

        if (file_exists($filename)) {
            Process::run([Sudo::find(), 'chmod', '--reference=' . $filename, $tempFilename]);
            Process::run([Sudo::find(), 'chown', '--reference=' . $filename, $tempFilename]);
        }

        Process::run([Sudo::find(), 'mv', $tempFilename, $filename]);
    }
}
