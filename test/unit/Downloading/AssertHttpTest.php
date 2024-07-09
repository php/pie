<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading;

use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Php\Pie\Downloading\AssertHttp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssertHttp::class)]
final class AssertHttpTest extends TestCase
{
    public function testResponseStatusCode(): void
    {
        $response = new Response(404, [], 'some body content');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected HTTP 201 response, got 404 - response: some body content');
        AssertHttp::responseStatusCode(201, $response);
    }
}
