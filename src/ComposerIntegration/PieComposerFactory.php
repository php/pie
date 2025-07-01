<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Composer;
use Composer\Factory;
use Composer\Installer;
use Composer\IO\IOInterface;
use Composer\PartialComposer;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;
use Php\Pie\ComposerIntegration\Listeners\OverrideDownloadUrlInstallListener;
use Php\Pie\ComposerIntegration\Listeners\RemoveUnrelatedInstallOperations;
use Php\Pie\ExtensionType;
use Php\Pie\Platform;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class PieComposerFactory extends Factory
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly PieComposerRequest $composerRequest,
    ) {
    }

    protected function createDefaultInstallers(Installer\InstallationManager $im, PartialComposer $composer, IOInterface $io, ProcessExecutor|null $process = null): void
    {
        $fs = new Filesystem($process);

        $installerFactory = function (ExtensionType $type) use ($io, $composer, $fs): PiePackageInstaller {
            return new PiePackageInstaller(
                $io,
                $composer,
                $type,
                $fs,
                $this->container->get(InstallAndBuildProcess::class),
                $this->container->get(UninstallProcess::class),
                $this->composerRequest,
            );
        };

        $im->addInstaller($installerFactory(ExtensionType::PhpModule));
        $im->addInstaller($installerFactory(ExtensionType::ZendExtension));
    }

    public static function createPieComposer(
        ContainerInterface $container,
        PieComposerRequest $composerRequest,
    ): Composer {
        $pieComposer = Platform::getPieJsonFilename($composerRequest->targetPlatform);

        PieJsonEditor::fromTargetPlatform($composerRequest->targetPlatform)->ensureExists();

        $io       = $container->get(QuieterConsoleIO::class);
        $composer = (new PieComposerFactory($container, $composerRequest))->createComposer(
            $io,
            $pieComposer,
            true,
        );

        $composer
            ->getRepositoryManager()
            ->addRepository(BundledPhpExtensionsRepository::forTargetPlatform(
                $composerRequest->targetPlatform,
            ));

        OverrideDownloadUrlInstallListener::selfRegister($composer, $io, $container, $composerRequest);
        RemoveUnrelatedInstallOperations::selfRegister($composer, $composerRequest);

        $composer->getConfig()->merge(['config' => ['__PIE_REQUEST__' => $composerRequest]]);
        $io->loadConfiguration($composer->getConfig());

        return $composer;
    }

    protected function purgePackages(InstalledRepositoryInterface $repo, Installer\InstallationManager $im): void
    {
        /**
         * This is intentionally a no-op in PIE....
         *
         * Why not purge packages?
         *
         * We have a post install job in {@see VendorCleanup} that cleans up the vendor directory to remove all the
         * actual package files; however, this means that Composer thinks they are not installed after that. When
         * creating the Composer instance, the last step is to purge packages from the
         * {@see InstalledRepositoryInterface} if they no longer exist on disk. But, that means we can't list the
         * packages installed with PIE any more! So, we override this method to become a no-op ✅
         */
    }

    public static function recreatePieComposer(
        ContainerInterface $container,
        Composer $existingComposer,
    ): Composer {
        $composerRequest = $existingComposer->getConfig()->get('__PIE_REQUEST__');

        Assert::isInstanceOf($composerRequest, PieComposerRequest::class);

        return self::createPieComposer($container, $composerRequest);
    }
}
