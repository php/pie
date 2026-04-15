<?php

declare(strict_types=1);

namespace Php\Pie\Platform\TargetPhp;

use Composer\IO\IOInterface;
use Composer\Semver\VersionParser;
use Composer\Util\Platform;
use Php\Pie\ExtensionName;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\DebugBuild;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\Exception\ExtensionPathProblem;
use Php\Pie\Util\Process;
use RuntimeException;
use Safe\Exceptions\FilesystemException;
use Symfony\Component\Process\PhpExecutableFinder;
use Webmozart\Assert\Assert;

use function array_combine;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function assert;
use function dirname;
use function explode;
use function file_exists;
use function implode;
use function in_array;
use function is_dir;
use function is_executable;
use function ltrim;
use function rtrim;
use function Safe\mkdir;
use function Safe\preg_match;
use function Safe\preg_replace;
use function sprintf;
use function strtolower;
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

    public function debugMode(): DebugBuild
    {
        if (
            preg_match('/Debug Build([ =>\t]*)(.*)/', $this->phpinfo(), $m)
            && $m[2] !== ''
        ) {
            return $m[2] === 'yes' ? DebugBuild::Debug : DebugBuild::NoDebug;
        }

        throw new RuntimeException('Failed to find PHP API version...');
    }

    /** @return non-empty-string */
    public function extensionPath(string|null $prefixInstallRoot = null): string
    {
        $phpinfo = $this->phpinfo();

        $extensionPath = null;

        if (
            preg_match('#^extension_dir\s+=>\s+([^=]+)\s+=>\s+([^=]+)$#m', $phpinfo, $matches)
            && trim($matches[1]) !== ''
            && trim($matches[1]) !== 'no value'
        ) {
            $extensionPath = trim($matches[1]);
            assert($extensionPath !== '');

            if (self::operatingSystem() !== OperatingSystem::Windows && $prefixInstallRoot !== null && $prefixInstallRoot !== '') {
                $extensionPath = rtrim($prefixInstallRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($extensionPath, DIRECTORY_SEPARATOR);
            }

            if (file_exists($extensionPath) && is_dir($extensionPath)) {
                return $extensionPath;
            }

            // `extension_dir` may be a relative URL on Windows, so resolve it according to the location of PHP
            if (self::operatingSystem() === OperatingSystem::Windows) {
                $phpPath              = dirname($this->phpBinaryPath);
                $attemptExtensionPath = $phpPath . DIRECTORY_SEPARATOR . $extensionPath;

                if (file_exists($attemptExtensionPath) && is_dir($attemptExtensionPath)) {
                    return $attemptExtensionPath;
                }
            }

            // if the path is absolute, try to create it
            try {
                mkdir($extensionPath, 0777, true);
            } catch (FilesystemException) {
                throw ExtensionPathProblem::new($this, $extensionPath);
            }

            if (file_exists($extensionPath) && is_dir($extensionPath)) {
                return $extensionPath;
            }
        }

        throw ExtensionPathProblem::new($this, $extensionPath);
    }

    public function assertExtensionIsLoadedInRuntime(ExtensionName $extension, IOInterface|null $io = null): void
    {
        if (! in_array(strtolower($extension->name()), array_map('strtolower', array_keys($this->extensions())))) {
            throw Exception\ExtensionIsNotLoaded::fromExpectedExtension(
                $this,
                $extension,
            );
        }

        if ($io === null) {
            return;
        }

        $io->write(
            sprintf(
                'Successfully asserted that extension %s is loaded in runtime.',
                $extension->name(),
            ),
            verbosity: IOInterface::VERBOSE,
        );
    }

    /** @return non-empty-string|null */
    public function additionalIniDirectory(): string|null
    {
        if (
            preg_match('/Scan this dir for additional \.ini files([ =>\t]*)(.*)/', $this->phpinfo(), $m)
            && array_key_exists(2, $m)
            && $m[2] !== ''
            && $m[2] !== '(none)'
        ) {
            return $m[2];
        }

        return null;
    }

    /** @return non-empty-string|null */
    public function loadedIniConfigurationFile(): string|null
    {
        if (
            preg_match('/Loaded Configuration File([ =>\t]*)(.*)/', $this->phpinfo(), $m)
            && array_key_exists(2, $m)
            && $m[2] !== ''
            && $m[2] !== '(none)'
        ) {
            return $m[2];
        }

        return null;
    }

    /** @return non-empty-string|null */
    public function buildProvider(): string|null
    {
        /**
         * Newer versions of PHP will have a `PHP_BUILD_PROVIDER` constant
         * defined - {@link https://github.com/php/php-src/pull/19157}
         */
        if (
            preg_match('/Build Provider([ =>\t]*)(.*)/', $this->phpinfo(), $m)
            && array_key_exists(2, $m)
            && $m[2] !== ''
            && $m[2] !== '(none)'
        ) {
            return $m[2];
        }

        return null;
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

    public function operatingSystemFamily(): OperatingSystemFamily
    {
        /** @link https://github.com/sebastianbergmann/environment/commit/eb6dd721cfaca04c27ac61c7201493a8f62f7f1d */
        $osFamily = OperatingSystemFamily::tryFrom(strtolower(trim(
            self::cleanWarningAndDeprecationsFromOutput(Process::run([
                $this->phpBinaryPath,
                '-r',
                <<<'PHP'
                if (defined('PHP_OS_FAMILY')) {
                    echo PHP_OS_FAMILY;
                } elseif ('\\' === DIRECTORY_SEPARATOR) {
                    echo 'Windows';
                } else {
                    switch(PHP_OS) {
                        case 'Darwin':
                            echo 'Darwin';
                            break;
                        case 'DragonFly':
                        case 'FreeBSD':
                        case 'NetBSD':
                        case 'OpenBSD':
                            echo 'BSD';
                            break;
                        case 'Linux':
                            echo 'Linux';
                            break;
                        case 'SunOS':
                            echo 'Solaris';
                            break;
                        default:
                            echo 'Unknown';
                    }
                }
                PHP,
            ])),
        )));
        Assert::notNull($osFamily, 'Could not determine operating system family');

        return $osFamily;
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
    public function phpVersionWithExtra(): string
    {
        $phpVersionWithExtra = self::cleanWarningAndDeprecationsFromOutput(Process::run([
            $this->phpBinaryPath,
            '-r',
            'echo PHP_VERSION;',
        ]));
        Assert::stringNotEmpty($phpVersionWithExtra, 'Could not determine PHP_VERSION');

        return $phpVersionWithExtra;
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

    public function majorVersion(): int
    {
        $phpVersion = self::cleanWarningAndDeprecationsFromOutput(Process::run([
            $this->phpBinaryPath,
            '-r',
            'echo PHP_MAJOR_VERSION;',
        ]));
        Assert::stringNotEmpty($phpVersion, 'Could not determine PHP version');

        return (int) $phpVersion;
    }

    public function minorVersion(): int
    {
        $phpVersion = self::cleanWarningAndDeprecationsFromOutput(Process::run([
            $this->phpBinaryPath,
            '-r',
            'echo PHP_MINOR_VERSION;',
        ]));
        Assert::stringNotEmpty($phpVersion, 'Could not determine PHP version');

        return (int) $phpVersion;
    }

    public function machineType(): Architecture
    {
        /**
         * On Windows, this will be x32 or x64; should not be used on other platforms
         *
         * Based on xdebug.org wizard, copyright Derick Rethans, used under MIT licence
         *
         * @link https://github.com/xdebug/xdebug.org/blob/aff649f2c3ca303ad471e6ed9dd29c0db16d3e22/src/XdebugVersion.php#L186-L190
         */
        if (
            $this->operatingSystem() === OperatingSystem::Windows
            && preg_match('/Architecture([ =>\t]*)(x[0-9]*)/', $this->phpinfo(), $m)
        ) {
            return Architecture::parseArchitecture($m[2]);
        }

        $phpMachineType = self::cleanWarningAndDeprecationsFromOutput(Process::run([
            $this->phpBinaryPath,
            '-r',
            'echo php_uname("m");',
        ]));
        Assert::stringNotEmpty($phpMachineType, 'Could not determine PHP machine type');

        $unameArchitecture = Architecture::parseArchitecture($phpMachineType);

        // If we're not on ARM, a more reliable way of determining 32-bit/64-bit is to use PHP_INT_SIZE
        if ($unameArchitecture !== Architecture::arm64) {
            return $this->phpIntSize() === 4 ? Architecture::x86 : Architecture::x86_64;
        }

        return $unameArchitecture;
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

        return self::guessWithPhpConfig(new self($phpExecutable, null));
    }

    private static function guessWithPhpConfig(self $phpBinaryPath): self
    {
        $phpConfigAttempts = [];

        // Try to add `phpize` from path
        $whichPhpize = new \Symfony\Component\Process\Process(['which', 'php-config']);
        if ($whichPhpize->run() === 0) {
            $phpConfigAttempts[] = trim($whichPhpize->getOutput());
        }

        $phpConfigAttempts[] = preg_replace('((.*)php)', '$1php-config', $phpBinaryPath->phpBinaryPath);

        foreach ($phpConfigAttempts as $phpConfigAttempt) {
            assert($phpConfigAttempt !== '');
            if (! file_exists($phpConfigAttempt) || ! is_executable($phpConfigAttempt)) {
                continue;
            }

            $phpizeProcess = new \Symfony\Component\Process\Process([$phpConfigAttempt, '--php-binary']);
            if ($phpizeProcess->run() !== 0) {
                continue;
            }

            if (trim($phpizeProcess->getOutput()) !== $phpBinaryPath->phpBinaryPath) {
                continue;
            }

            $phpConfigApiVersionProcess = new \Symfony\Component\Process\Process([$phpConfigAttempt, '--phpapi']);

            // older versions of php-config did not have `--phpapi`, so we can't perform this validation
            if ($phpConfigApiVersionProcess->run() !== 0) {
                return new self($phpBinaryPath->phpBinaryPath, $phpConfigAttempt);
            }

            if (trim($phpConfigApiVersionProcess->getOutput()) === $phpBinaryPath->phpApiVersion()) {
                return new self($phpBinaryPath->phpBinaryPath, $phpConfigAttempt);
            }
        }

        return $phpBinaryPath;
    }

    private static function cleanWarningAndDeprecationsFromOutput(string $testOutput): string
    {
        // Note: xdebug can prefix `PHP ` onto warnings/deprecations, so filter them out too
        return trim(implode(
            "\n",
            array_filter(
                explode("\n", $testOutput),
                static fn (string $line) => ! preg_match('/^(Deprecated|Warning|PHP Warning|PHP Deprecated):/', $line),
            ),
        ));
    }
}
