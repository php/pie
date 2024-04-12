<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function assert;
use function file_put_contents;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class DownloadZip
{
    public function __construct(
        private readonly ClientInterface $client,
    ) {
    }

    public function downloadZipAndReturnLocalPath(RequestInterface $request, string $localPath): string
    {
        $response = $this->client
            ->sendAsync(
                $request,
                [
                    RequestOptions::ALLOW_REDIRECTS => true,
                    RequestOptions::HTTP_ERRORS => false,
                    RequestOptions::SYNCHRONOUS => true,
                ],
            )
            ->wait();
        assert($response instanceof ResponseInterface);

        // @todo check response was successful

        // @todo handle this writing better
        $tmpZipFile = $localPath . '/downloaded.zip';
        file_put_contents($tmpZipFile, $response->getBody()->__toString());

        return $tmpZipFile;
    }
}
