<?php

declare(strict_types=1);

namespace Php\Pie\Platform\TargetPhp;

use Composer\Semver\VersionParser;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

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
