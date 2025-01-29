<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration\Listeners;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackageInterface;
use Composer\Util\AuthHelper;
use Composer\Util\HttpDownloader;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\PackageReleaseAssets;
use Php\Pie\Platform\OperatingSystem;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function reset;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class OverrideWindowsUrlInstallListener
{
    public function __construct(
        private readonly Composer $composer,
        private readonly IOInterface $io,
        private readonly ContainerInterface $container,
        private readonly PieComposerRequest $composerRequest,
    ) {
    }

    public static function selfRegister(
        Composer $composer,
        IOInterface $io,
        ContainerInterface $container,
        PieComposerRequest $composerRequest,
    ): void {
        $composer
            ->getEventDispatcher()
            ->addListener(
                InstallerEvents::PRE_OPERATIONS_EXEC,
                new self($composer, $io, $container, $composerRequest),
            );
    }

    public function __invoke(InstallerEvent $installerEvent): void
    {
        if ($this->composerRequest->targetPlatform->operatingSystem !== OperatingSystem::Windows) {
            return;
        }

        /** @psalm-suppress InternalMethod */
        $operations = $installerEvent->getTransaction()?->getOperations() ?? [];

        Assert::count($operations, 1, 'I can only do exactly %d thing at once, %d attempted');
        $operation = reset($operations);
        Assert::isInstanceOf($operation, InstallOperation::class, 'I can only handle %2$s, got %s');

        $composerPackage = $operation->getPackage();
        Assert::isInstanceOf($composerPackage, CompletePackageInterface::class, 'I can only handle %2$s, got %s');

        $packageReleaseAssets = $this->container->get(PackageReleaseAssets::class);
        $url                  = $packageReleaseAssets->findWindowsDownloadUrlForPackage(
            $this->composerRequest->targetPlatform,
            Package::fromComposerCompletePackage($composerPackage),
            new AuthHelper($this->io, $this->composer->getConfig()),
            new HttpDownloader($this->io, $this->composer->getConfig()),
        );

        $this->composerRequest->pieOutput->writeln('Found prebuilt archive: ' . $url);
        $composerPackage->setDistUrl($url);
    }
}
