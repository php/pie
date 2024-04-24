<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

use function sprintf;

class AssertHttp
{
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
