<?php

declare(strict_types=1);

namespace Php\Pie;

use Composer\Util\Platform;
use Illuminate\Container\Container as IlluminateContainer;
use Php\Pie\Building\Build;
use Php\Pie\Building\UnixBuild;
use Php\Pie\Building\WindowsBuild;
use Php\Pie\Command\BuildCommand;
use Php\Pie\Command\DownloadCommand;
use Php\Pie\Command\InfoCommand;
use Php\Pie\Command\InstallCommand;
use Php\Pie\Command\RepositoryAddCommand;
use Php\Pie\Command\RepositoryListCommand;
use Php\Pie\Command\RepositoryRemoveCommand;
use Php\Pie\Command\ShowCommand;
use Php\Pie\ComposerIntegration\MinimalHelperSet;
use Php\Pie\ComposerIntegration\QuieterConsoleIO;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\DependencyResolver\ResolveDependencyWithComposer;
use Php\Pie\Downloading\GithubPackageReleaseAssets;
use Php\Pie\Downloading\PackageReleaseAssets;
use Php\Pie\Installing\Ini;
use Php\Pie\Installing\Install;
use Php\Pie\Installing\UnixInstall;
use Php\Pie\Installing\WindowsInstall;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class Container
{
    public static function factory(): ContainerInterface
    {
        $container = new IlluminateContainer();
        $container->instance(ContainerInterface::class, $container);
        $container->instance(InputInterface::class, new ArgvInput());
        $container->instance(OutputInterface::class, new ConsoleOutput());

        $container->singleton(DownloadCommand::class);
        $container->singleton(BuildCommand::class);
        $container->singleton(InstallCommand::class);
        $container->singleton(InfoCommand::class);
        $container->singleton(ShowCommand::class);
        $container->singleton(RepositoryListCommand::class);
        $container->singleton(RepositoryAddCommand::class);
        $container->singleton(RepositoryRemoveCommand::class);

        $container->singleton(QuieterConsoleIO::class, static function (ContainerInterface $container): QuieterConsoleIO {
            return new QuieterConsoleIO(
                $container->get(InputInterface::class),
                $container->get(OutputInterface::class),
                new MinimalHelperSet(
                    [
                        'question' => new QuestionHelper(),
                    ],
                ),
            );
        });

        $container->alias(ResolveDependencyWithComposer::class, DependencyResolver::class);

        $container->alias(GithubPackageReleaseAssets::class, PackageReleaseAssets::class);
        $container->when(GithubPackageReleaseAssets::class)
            ->needs('$githubApiBaseUrl')
            ->give('https://api.github.com');

        $container->singleton(
            Build::class,
            static function (ContainerInterface $container): Build {
                if (Platform::isWindows()) {
                    return $container->get(WindowsBuild::class);
                }

                return $container->get(UnixBuild::class);
            },
        );

        $container->singleton(
            Ini\SetupIniApproach::class,
            static function (ContainerInterface $container): Ini\SetupIniApproach {
                return new Ini\PickBestSetupIniApproach([
                    $container->get(Ini\PreCheckExtensionAlreadyLoaded::class),
                    $container->get(Ini\OndrejPhpenmod::class),
                    $container->get(Ini\DockerPhpExtEnable::class),
                    $container->get(Ini\StandardAdditionalPhpIniDirectory::class),
                    $container->get(Ini\StandardSinglePhpIni::class),
                ]);
            },
        );

        $container->singleton(
            Install::class,
            static function (ContainerInterface $container): Install {
                if (Platform::isWindows()) {
                    return $container->get(WindowsInstall::class);
                }

                return $container->get(UnixInstall::class);
            },
        );

        return $container;
    }
}
