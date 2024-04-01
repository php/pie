<?php

declare(strict_types=1);

namespace Php\Pie\TargetPhp;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class PhpBinaryPath
{
    /** @param non-empty-string $phpBinaryPath */
    private function __construct(readonly string $phpBinaryPath)
    {
    }

    public function version(): string
    {
        $phpVersion = trim((new Process([$this->phpBinaryPath, '-r', 'echo phpversion();']))
            ->mustRun()
            ->getOutput());
        Assert::stringNotEmpty($phpVersion, 'Could not determine PHP version');
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
        $phpExecutable = trim((new PhpExecutableFinder())->find());
        Assert::stringNotEmpty($phpExecutable, 'Could not find path to PHP executable.');
        return new self($phpExecutable);
    }
}
