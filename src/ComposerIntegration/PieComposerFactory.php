<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Composer;
use Composer\Factory;
use Composer\Installer;
use Composer\IO\IOInterface;
use Composer\PartialComposer;
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

        OverrideDownloadUrlInstallListener::selfRegister($composer, $io, $container, $composerRequest);
        RemoveUnrelatedInstallOperations::selfRegister($composer, $composerRequest);

        $composer->getConfig()->merge(['config' => ['__PIE_REQUEST__' => $composerRequest]]);
        $io->loadConfiguration($composer->getConfig());

        return $composer;
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
