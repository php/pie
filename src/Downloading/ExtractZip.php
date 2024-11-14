<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use PharData;
use RuntimeException;
use ZipArchive;

use function explode;
use function extension_loaded;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class ExtractZip
{
    public function to(string $zipFile, string $destination): string
    {
        if (! extension_loaded('zip')) {
            return $this->performExtractionUsingPharData($zipFile, $destination);
        }

        return $this->performExtractionUsingZipArchive($zipFile, $destination);
    }

    private function performExtractionUsingZipArchive(string $zipFile, string $destination): string
    {
        $zip = new ZipArchive();

        $openError = $zip->open($zipFile);
        if ($openError !== true) {
            throw new RuntimeException(sprintf(
                'Could not open ZIP [%s]: %s',
                $openError === false ? '(false)' : $openError,
                $zipFile,
            ));
        }

        if (! $zip->extractTo($destination)) {
            throw new RuntimeException(sprintf('Could not extract ZIP "%s" to path: %s', $zipFile, $destination));
        }

        // @todo maybe improve this; GH wraps archives in a top level directory based on the repo name
        //       and commit, but does anyone else? :s
        $extractedPath = explode('/', $zip->getNameIndex(0))[0];

        $zip->close();

        return $destination . '/' . $extractedPath;
    }

    private function performExtractionUsingPharData(string $zipFile, string $destination): string
    {
        try {
            $phar = new PharData($zipFile);
            $phar->extractTo($destination, overwrite: true);

            /** @see ExtractZip::performExtractionUsingZipArchive todo item */
            $extractedPath = explode('/', $phar->current()->getFileName())[0];
        } catch (RuntimeException $e) {
            throw new RuntimeException(sprintf('Could not extract ZIP "%s" to path: %s', $zipFile, $destination), 0, $e);
        }

        return $destination . '/' . $extractedPath;
    }
}
