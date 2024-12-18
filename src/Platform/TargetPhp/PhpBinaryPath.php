<?php

declare(strict_types=1);

namespace Php\Pie\Platform\TargetPhp;

use Composer\Semver\VersionParser;
use Composer\Util\Platform;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Util\Process;
use RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;
use Webmozart\Assert\Assert;

use function array_combine;
use function array_key_exists;
use function array_map;
use function assert;
use function dirname;
use function explode;
use function file_exists;
use function implode;
use function is_dir;
use function is_executable;
use function preg_match;
use function sprintf;
use function trim;

use const DIRECTORY_SEPARATOR;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
class PhpBinaryPath
{
    /**
     * @param non-empty-string      $phpBinaryPath
     * @param non-empty-string|null $phpConfigPath
     */
    private function __construct(
        public readonly string $phpBinaryPath,
        private readonly string|null $phpConfigPath,
    ) {
    }

    /** @param non-empty-string $phpBinaryPath */
    private static function assertValidLookingPhpBinary(string $phpBinaryPath): void
    {
        if (! file_exists($phpBinaryPath)) {
            throw Exception\InvalidPhpBinaryPath::fromNonExistentPhpBinary($phpBinaryPath);
        }

        if (! Platform::isWindows() && ! is_executable($phpBinaryPath)) {
            throw Exception\InvalidPhpBinaryPath::fromNonExecutablePhpBinary($phpBinaryPath);
        }

        // This is somewhat of a rudimentary check that the target PHP really is a PHP instance; not sure why you
        // WOULDN'T want to use a real PHP, but this should stop obvious hiccups at least (rather than for security)
        $testOutput = self::cleanWarningAndDeprecationsFromOutput(Process::run([$phpBinaryPath, '-r', 'echo "PHP";']));

        if ($testOutput !== 'PHP') {
            throw Exception\InvalidPhpBinaryPath::fromInvalidPhpBinary($phpBinaryPath);
        }
    }

    /** @return non-empty-string */
    public function phpApiVersion(): string
    {
        if (
            preg_match('/PHP API([ =>\t]*)(.*)/', $this->phpinfo(), $m)
            && array_key_exists(2, $m)
            && $m[2] !== ''
        ) {
            return $m[2];
        }

        throw new RuntimeException('Failed to find PHP API version...');
    }

    /** @return non-empty-string */
    public function extensionPath(): string
    {
        $phpinfo = $this->phpinfo();

        if (
            preg_match('#^extension_dir\s+=>\s+([^=]+)\s+=>\s+([^=]+)$#m', $phpinfo, $matches)
            && array_key_exists(1, $matches)
            && trim($matches[1]) !== ''
            && trim($matches[1]) !== 'no value'
        ) {
            $extensionPath = trim($matches[1]);
            assert($extensionPath !== '');

            if (file_exists($extensionPath) && is_dir($extensionPath)) {
                return $extensionPath;
            }

            // `extension_dir` may be a relative URL on Windows, so resolve it according to the location of PHP
            $phpPath              = dirname($this->phpBinaryPath);
            $attemptExtensionPath = $phpPath . DIRECTORY_SEPARATOR . $extensionPath;

            if (file_exists($attemptExtensionPath) && is_dir($attemptExtensionPath)) {
                return $attemptExtensionPath;
            }
        }

        throw new RuntimeException('Could not determine extension path for ' . $this->phpBinaryPath);
    }

    /**
     * Returns a map where the key is the name of the extension and the value is the version ('0' if not defined)
     *
     * @return array<string, string>
     */
    public function extensions(): array
    {
        $extVersionsList = self::cleanWarningAndDeprecationsFromOutput(Process::run([
            $this->phpBinaryPath,
            '-r',
            <<<'PHP'
$exts = get_loaded_extensions();
$extVersions = array_map(
    static function ($extension) {
        $extVersion = phpversion($extension);
        if ($extVersion === false) {
            return '0';
        }
        return $extVersion;
    },
    $exts
);
echo implode("\n", array_map(
    static function ($k, $v) {
        return sprintf('%s:%s', $k, $v);
    },
    $exts,
    $extVersions
));
PHP,
        ]));

        $pairs = array_map(
            static fn (string $row) => explode(':', $row),
            explode("\n", $extVersionsList),
        );

        return array_combine(
            array_map(static fn (array $row) => $row[0], $pairs),
            array_map(static fn (array $row) => $row[1], $pairs),
        );
    }

    public function operatingSystem(): OperatingSystem
    {
        $winOrNot = self::cleanWarningAndDeprecationsFromOutput(Process::run([
            $this->phpBinaryPath,
            '-r',
            'echo \\defined(\'PHP_WINDOWS_VERSION_BUILD\') ? \'win\' : \'not\';',
        ]));
        Assert::stringNotEmpty($winOrNot, 'Could not determine PHP version');

        return $winOrNot === 'win' ? OperatingSystem::Windows : OperatingSystem::NonWindows;
    }

    /** @return non-empty-string */
    public function version(): string
    {
        $phpVersion = self::cleanWarningAndDeprecationsFromOutput(Process::run([
            $this->phpBinaryPath,
            '-r',
            'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION . "." . PHP_RELEASE_VERSION;',
        ]));
        Assert::stringNotEmpty($phpVersion, 'Could not determine PHP version');

        // normalizing the version will throw an exception if it is not a valid version
        (new VersionParser())->normalize($phpVersion);

        return $phpVersion;
    }

    /** @return non-empty-string */
    public function majorMinorVersion(): string
    {
        $phpVersion = self::cleanWarningAndDeprecationsFromOutput(Process::run([
            $this->phpBinaryPath,
            '-r',
            'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;',
        ]));
        Assert::stringNotEmpty($phpVersion, 'Could not determine PHP version');

        // normalizing the version will throw an exception if it is not a valid version
        (new VersionParser())->normalize($phpVersion);

        return $phpVersion;
    }

    public function machineType(): Architecture
    {
        $phpMachineType = self::cleanWarningAndDeprecationsFromOutput(Process::run([
            $this->phpBinaryPath,
            '-r',
            'echo php_uname("m");',
        ]));
        Assert::stringNotEmpty($phpMachineType, 'Could not determine PHP machine type');

        return Architecture::parseArchitecture($phpMachineType);
    }

    public function phpIntSize(): int
    {
        $phpIntSize = self::cleanWarningAndDeprecationsFromOutput(Process::run([
            $this->phpBinaryPath,
            '-r',
            'echo PHP_INT_SIZE;',
        ]));
        Assert::stringNotEmpty($phpIntSize, 'Could not fetch PHP_INT_SIZE');
        Assert::same($phpIntSize, (string) (int) $phpIntSize, 'PHP_INT_SIZE was not an integer processed %2$s from %s');

        return (int) $phpIntSize;
    }

    /** @return non-empty-string */
    public function phpinfo(): string
    {
        $phpInfo = self::cleanWarningAndDeprecationsFromOutput(Process::run([
            $this->phpBinaryPath,
            '-i',
        ]));

        Assert::stringNotEmpty($phpInfo, sprintf('Could not run phpinfo using %s', $this->phpBinaryPath));

        return $phpInfo;
    }

    /**
     * This will only be set if {@see self::fromPhpConfigExecutable()} is used to create this {@see self}, otherwise
     * will return `null`.
     *
     * @return non-empty-string|null
     */
    public function phpConfigPath(): string|null
    {
        return $this->phpConfigPath;
    }

    /** @param non-empty-string $phpConfig */
    public static function fromPhpConfigExecutable(string $phpConfig): self
    {
        $phpExecutable = self::cleanWarningAndDeprecationsFromOutput(Process::run([$phpConfig, '--php-binary']));
        Assert::stringNotEmpty($phpExecutable, 'Could not find path to PHP executable.');

        self::assertValidLookingPhpBinary($phpExecutable);

        return new self($phpExecutable, $phpConfig);
    }

    /** @param non-empty-string $phpBinary */
    public static function fromPhpBinaryPath(string $phpBinary): self
    {
        self::assertValidLookingPhpBinary($phpBinary);

        return new self($phpBinary, null);
    }

    public static function fromCurrentProcess(): self
    {
        $phpExecutable = trim((string) (new PhpExecutableFinder())->find());
        Assert::stringNotEmpty($phpExecutable, 'Could not find path to PHP executable.');

        self::assertValidLookingPhpBinary($phpExecutable);

        return new self($phpExecutable, null);
    }

    private static function cleanWarningAndDeprecationsFromOutput(string $testOutput): string
    {
        $testOutput = explode("\n", $testOutput);

        foreach ($testOutput as $key => $line) {
            if (! preg_match('/^(Deprecated|Warning):/', $line)) {
                continue;
            }

            unset($testOutput[$key]);
        }

        return implode("\n", $testOutput);
    }
}
