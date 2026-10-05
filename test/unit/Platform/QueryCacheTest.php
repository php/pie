<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform;

use Php\Pie\ExtensionName;
use Php\Pie\Platform\PkgConfig;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function escapeshellarg;
use function getenv;
use function Safe\chmod;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\putenv;
use function Safe\tempnam;
use function Safe\unlink;
use function substr_count;
use function sys_get_temp_dir;

use const PHP_OS_FAMILY;

#[CoversClass(PhpBinaryPath::class)]
#[CoversClass(PkgConfig::class)]
final class QueryCacheTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            unlink($file);
        }
    }

    public function testPhpQueriesAreReusedButRuntimeInformationCanBeRefreshed(): void
    {
        $calls = $this->temporaryFile();
        $state = $this->temporaryFile();
        file_put_contents($state, 'demo:1.0');
        $executable = $this->executable(<<<'SH'
printf 'call\n' >> "$PIE_TEST_CALLS"
if [ "$1" = '-i' ]; then
    cat "$PIE_TEST_STATE"
elif [ "$2" = 'echo "PHP";' ]; then
    printf PHP
elif [ "$2" = 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION . "." . PHP_RELEASE_VERSION;' ]; then
    printf 8.4.26
else
    cat "$PIE_TEST_STATE"
fi
SH, $calls, $state);

        $php = PhpBinaryPath::fromPhpBinaryPath($executable);
        self::assertSame('8.4.26', $php->version());
        self::assertSame('8.4.26', $php->version());
        self::assertSame(['demo' => '1.0'], $php->extensions());
        self::assertSame('demo:1.0', $php->phpinfo());
        file_put_contents($state, 'demo:2.0');
        self::assertSame(['demo' => '1.0'], $php->extensions());
        self::assertSame('demo:1.0', $php->phpinfo());
        self::assertSame(4, $this->callCount($calls));

        $php->refreshRuntimeInformation();
        self::assertSame('8.4.26', $php->version());
        self::assertSame(['demo' => '2.0'], $php->extensions());
        self::assertSame('demo:2.0', $php->phpinfo());
        self::assertSame(6, $this->callCount($calls));

        // Separate target objects must not share results.
        $second = PhpBinaryPath::fromPhpBinaryPath($executable);
        self::assertSame(['demo' => '2.0'], $second->extensions());
        self::assertSame(8, $this->callCount($calls));

        // Verification after enabling an extension must query the target again.
        $php->assertExtensionIsLoadedInRuntime(ExtensionName::normaliseFromString('demo'));
        self::assertSame(9, $this->callCount($calls));
    }

    public function testPkgConfigCachesSuccessAndFailureAndRefreshesAfterInstallation(): void
    {
        $calls = $this->temporaryFile();
        $state = $this->temporaryFile();
        file_put_contents($state, 'demo=1.0');
        $executable = $this->executable(<<<'SH'
printf 'call\n' >> "$PIE_TEST_CALLS"
if [ ! -s "$PIE_TEST_STATE" ]; then
    exit 1
fi
cat "$PIE_TEST_STATE"
SH, $calls, $state);

        $pkgConfig = new PkgConfig($executable);
        self::assertSame('demo=1.0', $pkgConfig->provides('demo'));
        self::assertSame('demo=1.0', $pkgConfig->provides('demo'));
        self::assertSame(1, $this->callCount($calls));

        $pkgConfig->clear();
        file_put_contents($state, '');
        self::assertNull($pkgConfig->provides('demo'));
        self::assertNull($pkgConfig->provides('demo'));
        self::assertSame(2, $this->callCount($calls));

        file_put_contents($state, 'demo=2.0');
        $pkgConfig->clear();
        self::assertSame('demo=2.0', $pkgConfig->provides('demo'));
        self::assertSame(3, $this->callCount($calls));

        $original = getenv('PKG_CONFIG_PATH');
        try {
            putenv('PKG_CONFIG_PATH=/example/changed');
            self::assertSame('demo=2.0', $pkgConfig->provides('demo'));
            self::assertSame(4, $this->callCount($calls));
        } finally {
            putenv($original === false ? 'PKG_CONFIG_PATH' : 'PKG_CONFIG_PATH=' . $original);
        }
    }

    /** @phpstan-impure */
    private function callCount(string $path): int
    {
        return substr_count(file_get_contents($path), "\n");
    }

    /** @return non-empty-string */
    private function temporaryFile(): string
    {
        $file          = tempnam(sys_get_temp_dir(), 'pie-query-cache-');
        $this->files[] = $file;

        return $file;
    }

    /** @return non-empty-string */
    private function executable(string $body, string $calls, string $state): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('The subprocess fixtures require a POSIX shell.');
        }

        $file = $this->temporaryFile();
        file_put_contents($file, "#!/bin/sh\nPIE_TEST_CALLS=" . escapeshellarg($calls) . "\nPIE_TEST_STATE=" . escapeshellarg($state) . "\n" . $body . "\n");
        chmod($file, 0700);

        return $file;
    }
}
