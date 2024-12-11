<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Composer;
use Composer\Installer;
use Composer\IO\IOInterface;
use Composer\Repository\PlatformRepository;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Webmozart\Assert\Assert;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @psalm-suppress PropertyNotSetInConstructor Property $fixedRootPackage is defined in parent
 */
class PieComposerInstaller extends Installer
{
    private PhpBinaryPath|null $phpBinaryPath = null;

    protected function createPlatformRepo(bool $forUpdate): PlatformRepository
    {
        Assert::notNull($this->phpBinaryPath, 'PHP Binary path was not set, maybe createWithPhpBinary was not used?');

        return new PhpBinaryPathBasedPlatformRepository($this->phpBinaryPath);
    }

    public static function createWithPhpBinary(PhpBinaryPath $php, IOInterface $io, Composer $composer): self
    {
        /** @psalm-suppress InvalidArgument some kind of unrelated type mismatch, defined in parent */
        $composerInstaller = new self(
            $io,
            $composer->getConfig(),
            $composer->getPackage(),
            $composer->getDownloadManager(),
            $composer->getRepositoryManager(),
            $composer->getLocker(),
            $composer->getInstallationManager(),
            $composer->getEventDispatcher(),
            $composer->getAutoloadGenerator(),
        );

        $composerInstaller->phpBinaryPath = $php;

        return $composerInstaller;
    }
}
