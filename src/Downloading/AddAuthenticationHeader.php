<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Composer\Util\AuthHelper;
use Php\Pie\DependencyResolver\Package;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

use function array_walk;
use function explode;
use function sprintf;
use function trim;

final class AddAuthenticationHeader
{
    public static function withAuthHeaderFromComposer(RequestInterface $request, Package $package, AuthHelper $authHelper): RequestInterface
    {
        if ($package->downloadUrl === null) {
            throw new RuntimeException(sprintf('The package %s does not have a download URL', $package->name));
        }

        $authHeaders = $authHelper->addAuthenticationHeader([], 'github.com', $package->downloadUrl);
        array_walk(
            $authHeaders,
            static function (string $v) use (&$request): void {
                // @todo probably process this better
                $headerParts = explode(':', $v);
                $request     = $request->withHeader(trim($headerParts[0]), trim($headerParts[1]));
            },
        );

        return $request;
    }
}
