<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration;

use Php\Pie\ComposerIntegration\ComposerRunFailed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComposerRunFailed::class)]
final class ComposerRunFailedTest extends TestCase
{
    /**
     * @return array<non-empty-string, array{0: int, 1: string, 2: int}>
     *
     * @psalm-suppress PossiblyUnusedMethod https://github.com/psalm/psalm-plugin-phpunit/issues/131
     */
    public static function errorCodesProvider(): array
    {
        return [
            'exit-0' => [0, 'PIE Composer run failed with error code 0', 1], // probably not real scenario
            'exit-1' => [1, 'PIE Composer run failed with error code 1 (ERROR_GENERIC_FAILURE)', 1],
            'exit-2' => [2, 'PIE Composer run failed with error code 2 (ERROR_DEPENDENCY_RESOLUTION_FAILED)', 2],
            'exit-3' => [3, 'PIE Composer run failed with error code 3 (ERROR_NO_LOCK_FILE_FOR_PARTIAL_UPDATE)', 3],
            'exit-4' => [4, 'PIE Composer run failed with error code 4 (ERROR_LOCK_FILE_INVALID)', 4],
            'exit-5' => [5, 'PIE Composer run failed with error code 5 (ERROR_AUDIT_FAILED)', 5],
            'exit-6' => [6, 'PIE Composer run failed with error code 6', 6],
            'exit-99' => [99, 'PIE Composer run failed with error code 99', 99],
            'exit-100' => [100, 'PIE Composer run failed with error code 100 (ERROR_TRANSPORT_EXCEPTION)', 100],
            'exit-101' => [101, 'PIE Composer run failed with error code 101', 101],
        ];
    }

    #[DataProvider('errorCodesProvider')]
    public function testErrorCodesMapped(int $exitCode, string $expectedMessage, int $expectedErrorCode): void
    {
        $exception = ComposerRunFailed::fromExitCode($exitCode);

        self::assertSame($expectedMessage, $exception->getMessage());
        self::assertSame($expectedErrorCode, $exception->getCode());
    }
}
