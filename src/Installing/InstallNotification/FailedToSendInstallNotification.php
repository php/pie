<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallNotification;

use Php\Pie\Downloading\DownloadedPackage;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function sprintf;

class FailedToSendInstallNotification extends RuntimeException
{
    public static function fromFailedResponse(
        DownloadedPackage $package,
        RequestInterface $request,
        ResponseInterface $response,
    ): self {
        return new self(sprintf(
            "Failed to notify package %s installation to %s [%d]:\n\n"
            . "Request: %s\n\n"
            . 'Response: %s',
            $package->package->prettyNameAndVersion(),
            $request->getUri(),
            $response->getStatusCode(),
            $request->getBody()->__toString(),
            $response->getBody()->__toString(),
        ));
    }
}
