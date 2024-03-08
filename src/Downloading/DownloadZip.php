<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Composer\Util\AuthHelper;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Php\Pie\DependencyResolver\Package;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function array_walk;
use function assert;
use function explode;
use function file_put_contents;
use function trim;

final class DownloadZip
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly AuthHelper $authHelper,
    ) {
    }

    public function downloadZipAndReturnLocalPath(Package $package, string $localPath): string
    {
        if ($package->downloadUrl === null) {
            throw new RuntimeException('Could not download a package without a download URL');
        }

        $request = new Request('GET', $package->downloadUrl);

        $authHeaders = $this->authHelper->addAuthenticationHeader([], 'github.com', $package->downloadUrl);
        array_walk(
            $authHeaders,
            static function (string $v) use (&$request): void {
                // @todo probably process this better
                $headerParts = explode(':', $v);
                $request     = $request->withHeader(trim($headerParts[0]), trim($headerParts[1]));
            },
        );

        assert($request instanceof RequestInterface);
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
