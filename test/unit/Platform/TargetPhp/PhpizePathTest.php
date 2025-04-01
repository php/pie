<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform\TargetPhp;

use Composer\Util\Platform;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPhp\PhpizePath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\PhpExecutableFinder;

use function array_combine;
use function array_filter;
use function array_map;
use function array_unique;
use function file_exists;
use function is_executable;

#[CoversClass(PhpizePath::class)]
final class PhpizePathTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function phpPathProvider(): array
    {
        $possiblePhpBinaries = array_filter(
            array_unique([
                '/usr/bin/php',
                (string) (new PhpExecutableFinder())->find(),
                '/usr/bin/php8.4',
                '/usr/bin/php8.3',
                '/usr/bin/php8.2',
                '/usr/bin/php8.1',
                '/usr/bin/php8.0',
                '/usr/bin/php7.4',
                '/usr/bin/php7.3',
                '/usr/bin/php7.2',
                '/usr/bin/php7.1',
                '/usr/bin/php7.0',
                '/usr/bin/php5.6',
            ]),
            static fn (string $phpPath) => file_exists($phpPath) && is_executable($phpPath),
        );

        return array_combine(
            $possiblePhpBinaries,
            array_map(static fn (string $phpPath) => [$phpPath], $possiblePhpBinaries),
        );
    }

    #[DataProvider('phpPathProvider')]
    public function testGuessingFindsPhpizePath(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Guessing phpize path is not done for Windows as we are not building for Windows.');
        }

        $phpize = PhpizePath::guessFrom(PhpBinaryPath::fromCurrentProcess());

        self::assertNotEmpty($phpize->phpizeBinaryPath);
    }
}
