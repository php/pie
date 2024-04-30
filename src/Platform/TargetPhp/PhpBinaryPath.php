<?php

declare(strict_types=1);

namespace Php\Pie\Platform\TargetPhp;

use Composer\Semver\VersionParser;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function sprintf;
use function trim;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
class PhpBinaryPath
{
    /** @param non-empty-string $phpBinaryPath */
    private function __construct(readonly string $phpBinaryPath)
    {
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

    public static function fromPhpConfigExecutable(string $phpConfig): self
    {
        // @todo filter input/sanitize output
        $phpExecutable = trim((new Process([$phpConfig, '--php-binary']))
            ->mustRun()
            ->getOutput());
        Assert::stringNotEmpty($phpExecutable, 'Could not find path to PHP executable.');

        return new self($phpExecutable);
    }

    public static function fromCurrentProcess(): self
    {
        $phpExecutable = trim((string) (new PhpExecutableFinder())->find());
        Assert::stringNotEmpty($phpExecutable, 'Could not find path to PHP executable.');

        return new self($phpExecutable);
    }
}
