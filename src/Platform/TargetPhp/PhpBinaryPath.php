<?php

declare(strict_types=1);

namespace Php\Pie\Platform\TargetPhp;

use Composer\Semver\VersionParser;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Psl\Json;
use Psl\Type;
use RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function assert;
use function dirname;
use function file_exists;
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
        if (! file_exists($this->phpBinaryPath)) {
            throw Exception\InvalidPhpBinaryPath::fromNonExistentPhpBinary($this->phpBinaryPath);
        }

        if (! is_executable($this->phpBinaryPath)) {
            throw Exception\InvalidPhpBinaryPath::fromNonExecutablePhpBinary($this->phpBinaryPath);
        }

        // This is somewhat of a rudimentary check that the target PHP really is a PHP instance; not sure why you
        // WOULDN'T want to use a real PHP, but this should stop obvious hiccups at least (rather than for security)
        $testOutput = trim((new Process([$this->phpBinaryPath, '-r', 'echo "PHP";']))
            ->mustRun()
            ->getOutput());

        if ($testOutput !== 'PHP') {
            throw Exception\InvalidPhpBinaryPath::fromInvalidPhpBinary($this->phpBinaryPath);
        }
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
        $extVersionsRawJson = trim((new Process([
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
echo json_encode(array_combine($exts, $extVersions));
PHP,
        ]))
            ->mustRun()
            ->getOutput());

        return Json\typed(
            $extVersionsRawJson,
            Type\dict(
                Type\string(),
                Type\string(),
            ),
        );
    }

    public function operatingSystem(): OperatingSystem
    {
        $winOrNot = trim((new Process([
            $this->phpBinaryPath,
            '-r',
            'echo \\defined(\'PHP_WINDOWS_VERSION_BUILD\') ? \'win\' : \'not\';',
        ]))
            ->mustRun()
            ->getOutput());
        Assert::stringNotEmpty($winOrNot, 'Could not determine PHP version');

        return $winOrNot === 'win' ? OperatingSystem::Windows : OperatingSystem::NonWindows;
    }

    /** @return non-empty-string */
    public function version(): string
    {
        $phpVersion = trim((new Process([
            $this->phpBinaryPath,
            '-r',
            'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION . "." . PHP_RELEASE_VERSION;',
        ]))
            ->mustRun()
            ->getOutput());
        Assert::stringNotEmpty($phpVersion, 'Could not determine PHP version');

        // normalizing the version will throw an exception if it is not a valid version
        (new VersionParser())->normalize($phpVersion);

        return $phpVersion;
    }

    /** @return non-empty-string */
    public function majorMinorVersion(): string
    {
        $phpVersion = trim((new Process([
            $this->phpBinaryPath,
            '-r',
            'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;',
        ]))
            ->mustRun()
            ->getOutput());
        Assert::stringNotEmpty($phpVersion, 'Could not determine PHP version');

        // normalizing the version will throw an exception if it is not a valid version
        (new VersionParser())->normalize($phpVersion);

        return $phpVersion;
    }

    public function machineType(): Architecture
    {
        $phpMachineType = trim((new Process([
            $this->phpBinaryPath,
            '-r',
            'echo php_uname("m");',
        ]))
            ->mustRun()
            ->getOutput());
        Assert::stringNotEmpty($phpMachineType, 'Could not determine PHP machine type');

        return Architecture::parseArchitecture($phpMachineType);
    }

    public function phpIntSize(): int
    {
        $phpIntSize = trim((new Process([
            $this->phpBinaryPath,
            '-r',
            'echo PHP_INT_SIZE;',
        ]))
            ->mustRun()
            ->getOutput());
        Assert::stringNotEmpty($phpIntSize, 'Could not fetch PHP_INT_SIZE');
        Assert::same($phpIntSize, (string) (int) $phpIntSize, 'PHP_INT_SIZE was not an integer processed %2$s from %s');

        return (int) $phpIntSize;
    }

    /** @return non-empty-string */
    public function phpinfo(): string
    {
        $phpInfo = trim((new Process([
            $this->phpBinaryPath,
            '-i',
        ]))
            ->mustRun()
            ->getOutput());

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
        $phpExecutable = trim((new Process([$phpConfig, '--php-binary']))
            ->mustRun()
            ->getOutput());
        Assert::stringNotEmpty($phpExecutable, 'Could not find path to PHP executable.');

        return new self($phpExecutable, $phpConfig);
    }

    /** @param non-empty-string $phpBinary */
    public static function fromPhpBinaryPath(string $phpBinary): self
    {
        return new self($phpBinary, null);
    }

    public static function fromCurrentProcess(): self
    {
        $phpExecutable = trim((string) (new PhpExecutableFinder())->find());
        Assert::stringNotEmpty($phpExecutable, 'Could not find path to PHP executable.');

        return new self($phpExecutable, null);
    }
}
