<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Composer;
use Composer\Installer;
use Composer\IO\IOInterface;
use Composer\Repository\PlatformRepository;
use Php\Pie\ExtensionName;
use Php\Pie\Platform\InstalledPiePackages;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Webmozart\Assert\Assert;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class PieComposerInstaller extends Installer
{
    private PhpBinaryPath|null $phpBinaryPath = null;
    /** @var list<ExtensionName> */
    private array $extensionsBeingInstalled = [];
    private Composer|null $composer         = null;

    protected function createPlatformRepo(bool $forUpdate): PlatformRepository
    {
        Assert::notNull($this->phpBinaryPath, '$phpBinaryPath was not set, maybe createWithPhpBinary was not used?');
        Assert::isNonEmptyList($this->extensionsBeingInstalled, '$extensionsBeingInstalled was not set, maybe createWithPhpBinary was not used?');
        Assert::allIsInstanceOf($this->extensionsBeingInstalled, ExtensionName::class, '$extensionsBeingInstalled were not all ExtensionName instances');
        Assert::notNull($this->composer, '$composer was not set, maybe createWithPhpBinary was not used?');

        return new PhpBinaryPathBasedPlatformRepository($this->phpBinaryPath, $this->composer, new InstalledPiePackages(), $this->extensionsBeingInstalled);
    }

    /** @param list<ExtensionName> $extensionsBeingInstalled */
    public static function createWithPhpBinary(
        PhpBinaryPath $php,
        array $extensionsBeingInstalled,
        IOInterface $io,
        Composer $composer,
    ): self {
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

        $composerInstaller->phpBinaryPath            = $php;
        $composerInstaller->extensionsBeingInstalled = $extensionsBeingInstalled;
        $composerInstaller->composer                 = $composer;

        return $composerInstaller;
    }
}
