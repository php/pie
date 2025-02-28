<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Util;

use Php\Pie\Util\CaptureErrors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function trigger_error;
use function uniqid;

use const E_USER_WARNING;

#[CoversClass(CaptureErrors::class)]
final class CaptureErrorsTest extends TestCase
{
    public function testErrorsAreCaptured(): void
    {
        $capturedErrors = [];
        $expectedResult = uniqid('expectedResult', true);

        $result = CaptureErrors::for(
            static function () use ($expectedResult): string {
                trigger_error('Something happened', E_USER_WARNING);

                return $expectedResult;
            },
            $capturedErrors,
        );

        self::assertSame($expectedResult, $result);
        self::assertCount(1, $capturedErrors);
        self::assertSame(E_USER_WARNING, $capturedErrors[0]['level']);
        self::assertSame('Something happened', $capturedErrors[0]['message']);
    }
}
