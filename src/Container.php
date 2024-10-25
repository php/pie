<?php

declare(strict_types=1);

namespace Php\Pie;

use Composer\Util\Platform;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Illuminate\Container\Container as IlluminateContainer;
use Php\Pie\Building\Build;
use Php\Pie\Building\UnixBuild;
use Php\Pie\Building\WindowsBuild;
use Php\Pie\Command\BuildCommand;
use Php\Pie\Command\DownloadCommand;
use Php\Pie\Command\InfoCommand;
use Php\Pie\Command\InstallCommand;
use Php\Pie\Command\ShowCommand;
use Php\Pie\ComposerIntegration\ArrayCollectionIO;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\DependencyResolver\ResolveDependencyWithComposer;
use Php\Pie\Downloading\DownloadAndExtract;
use Php\Pie\Downloading\DownloadZip;
use Php\Pie\Downloading\DownloadZipWithGuzzle;
use Php\Pie\Downloading\GithubPackageReleaseAssets;
use Php\Pie\Downloading\PackageReleaseAssets;
use Php\Pie\Downloading\UnixDownloadAndExtract;
use Php\Pie\Downloading\WindowsDownloadAndExtract;
use Php\Pie\Installing\Install;
use Php\Pie\Installing\InstallNotification\InstallNotification;
use Php\Pie\Installing\InstallNotification\SendInstallNotificationUsingGuzzle;
use Php\Pie\Installing\UnixInstall;
use Php\Pie\Installing\WindowsInstall;
use Psr\Container\ContainerInterface;
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

        $container->singleton(ArrayCollectionIO::class, static function (): ArrayCollectionIO {
            return new ArrayCollectionIO();
        });

        $container->alias(ResolveDependencyWithComposer::class, DependencyResolver::class);

        $container->bind(
            ClientInterface::class,
            static function (): ClientInterface {
                return new Client([RequestOptions::HTTP_ERRORS => false]);
            },
        );
        $container->alias(DownloadZipWithGuzzle::class, DownloadZip::class);
        $container->alias(GithubPackageReleaseAssets::class, PackageReleaseAssets::class);
        $container->when(GithubPackageReleaseAssets::class)
            ->needs('$githubApiBaseUrl')
            ->give('https://api.github.com');
        $container->singleton(
            DownloadAndExtract::class,
            static function (ContainerInterface $container): DownloadAndExtract {
                if (Platform::isWindows()) {
                    return $container->get(WindowsDownloadAndExtract::class);
                }

                return $container->get(UnixDownloadAndExtract::class);
            },
        );

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
            Install::class,
            static function (ContainerInterface $container): Install {
                if (Platform::isWindows()) {
                    return $container->get(WindowsInstall::class);
                }

                return $container->get(UnixInstall::class);
            },
        );

        $container->alias(SendInstallNotificationUsingGuzzle::class, InstallNotification::class);

        return $container;
    }
}
