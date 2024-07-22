<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Psr\Http\Message\RequestInterface;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
interface DownloadZip
{
    public const DOWNLOADED_ZIP_FILENAME = 'downloaded.zip';

    /**
     * @param non-empty-string $localPath
     *
     * @return non-empty-string
     */
    public function downloadZipAndReturnLocalPath(RequestInterface $request, string $localPath): string;
}
