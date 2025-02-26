<?php

declare(strict_types=1);

namespace Php\Pie\File;

use Php\Pie\Util\CaptureErrors;
use Php\Pie\Util\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use function file_put_contents;
use function fileperms;
use function is_writable;
use function sprintf;
use function substr;

final class SudoFilePut
{
    public static function contents(string $filename, string $content): void
    {
        $previousPermissions = substr(sprintf('%o', fileperms($filename)), -4);

        $didChangePermissions = self::attemptToMakeFileEditable($filename);

        $capturedErrors  = [];
        $writeSuccessful = CaptureErrors::for(
            static fn () => file_put_contents($filename, $content),
            $capturedErrors,
        );

        if ($writeSuccessful === false) {
            throw FailedToWriteFile::fromFilePutContentErrors($filename, $capturedErrors);
        }

        if (! $didChangePermissions || ! Sudo::exists()) {
            return;
        }

        Process::run([Sudo::find(), 'chmod', $previousPermissions, $filename]);
    }

    private static function attemptToMakeFileEditable(string $filename): bool
    {
        if (! Sudo::exists()) {
            return false;
        }

        if (! is_writable($filename)) {
            try {
                Process::run([Sudo::find(), 'chmod', '0777', $filename]);

                return true;
            } catch (ProcessFailedException) {
                return false;
            }
        }

        return false;
    }
}
