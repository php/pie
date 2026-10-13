<?php

declare(strict_types=1);

namespace Php\Pie\File;

use Php\Pie\Util\Process;
use Safe\Exceptions\FilesystemException;
use Symfony\Component\Process\Exception\ProcessFailedException;

use function dirname;
use function file_exists;
use function filegroup;
use function is_writable;
use function Safe\chmod;
use function Safe\file_put_contents;
use function Safe\fileowner;
use function Safe\fileperms;
use function Safe\rename;
use function Safe\tempnam;
use function Safe\unlink;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class ReplaceRunningExecutable
{
    public static function contents(string $filename, string $content): void
    {
        $directory = dirname($filename);

        if (is_writable($directory)) {
            self::replaceWithoutSudo($directory, $filename, $content);

            return;
        }

        self::replaceWithSudo($directory, $filename, $content);
    }

    private static function replaceWithoutSudo(string $directory, string $filename, string $content): void
    {
        $tempFilename = self::writeTempFile($directory, $filename, $content);

        try {
            rename($tempFilename, $filename);
        } catch (FilesystemException $e) {
            unlink($tempFilename);

            throw FailedToWriteFile::fromFilePutContentError($filename, $e);
        }
    }

    private static function replaceWithSudo(string $directory, string $filename, string $content): void
    {
        try {
            $sudo = Sudo::find();
        } catch (SudoNotFoundOnSystem $e) {
            throw FailedToWriteFile::fromNoPermissions($filename, $e);
        }

        $tempFilename   = self::writeTempFile(sys_get_temp_dir(), $filename, $content);
        $stagedFilename = $directory . DIRECTORY_SEPARATOR . uniqid('.pie_replace_', true);

        try {
            if (file_exists($filename)) {
                Process::run([$sudo, 'chown', self::ownerAndGroup($filename), $tempFilename], timeout: Process::SHORT_TIMEOUT);
            }

            // Moving into the target's directory first keeps the final rename atomic, even if the temp dir is another filesystem
            Process::run([$sudo, 'mv', $tempFilename, $stagedFilename], timeout: Process::SHORT_TIMEOUT);
            Process::run([$sudo, 'mv', $stagedFilename, $filename], timeout: Process::SHORT_TIMEOUT);
        } catch (ProcessFailedException | FilesystemException $e) {
            Process::run([$sudo, 'rm', '-f', $tempFilename, $stagedFilename], timeout: Process::SHORT_TIMEOUT);

            throw FailedToWriteFile::fromFilePutContentError($filename, $e);
        }
    }

    private static function ownerAndGroup(string $filename): string
    {
        $group = filegroup($filename);

        if ($group === false) {
            throw new FilesystemException('Could not determine the group of ' . $filename);
        }

        return fileowner($filename) . ':' . $group;
    }

    private static function writeTempFile(string $directory, string $filename, string $content): string
    {
        try {
            $tempFilename = tempnam($directory, '.pie_replace_');
        } catch (FilesystemException $e) {
            throw FailedToWriteFile::fromNoPermissions($filename, $e);
        }

        try {
            file_put_contents($tempFilename, $content);
            chmod($tempFilename, file_exists($filename) ? fileperms($filename) & 0777 : 0755);
        } catch (FilesystemException $e) {
            unlink($tempFilename);

            throw FailedToWriteFile::fromFilePutContentError($filename, $e);
        }

        return $tempFilename;
    }
}
