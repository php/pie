<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class AssertHttp
{
    /** Helper assertion that includes the HTTP response body when the HTTP status code does not match */
    public static function responseStatusCode(int $expectedStatusCode, ResponseInterface $response): void
    {
        $actualStatusCode = $response->getStatusCode();
        if ($actualStatusCode !== $expectedStatusCode) {
            throw new InvalidArgumentException(sprintf(
                'Expected HTTP %d response, got %d - response: %s',
                $expectedStatusCode,
                $actualStatusCode,
                $response->getBody()->__toString(),
            ));
        }
    }
}
