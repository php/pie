<?php

declare(strict_types=1);

namespace Php\Pie;

use Composer\Composer;
use Composer\Factory as ComposerFactory;
use Composer\IO\ConsoleIO;
use Composer\IO\IOInterface;
use Composer\Repository\CompositeRepository;
use Composer\Repository\RepositorySet;
use Composer\Util\AuthHelper;
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
use Php\Pie\Command\InstallCommand;
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
use Php\Pie\Installing\UnixInstall;
use Php\Pie\Platform\TargetPhp\ResolveTargetPhpToPlatformRepository;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\Console\Helper\HelperSet;
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
        $container->instance(InputInterface::class, new ArgvInput());
        $container->instance(OutputInterface::class, new ConsoleOutput());

        $container->singleton(DownloadCommand::class);
        $container->singleton(BuildCommand::class);
        $container->singleton(InstallCommand::class);

        $container->singleton(IOInterface::class, static function (ContainerInterface $container): IOInterface {
            return new ConsoleIO(
                $container->get(InputInterface::class),
                $container->get(OutputInterface::class),
                new HelperSet([]),
            );
        });
        $container->singleton(Composer::class, static function (ContainerInterface $container): Composer {
            $io       = $container->get(IOInterface::class);
            $composer = (new ComposerFactory())->createComposer(
                $io,
                [
                    'config' => ['lock' => false],
                ],
                true,
            );
            $io->loadConfiguration($composer->getConfig());

            return $composer;
        });

        $container->singleton(
            DependencyResolver::class,
            static function (ContainerInterface $container): DependencyResolver {
                $composer      = $container->get(Composer::class);
                $repositorySet = new RepositorySet();
                $repositorySet->addRepository(new CompositeRepository($composer->getRepositoryManager()->getRepositories()));

                return new ResolveDependencyWithComposer(
                    $repositorySet,
                    new ResolveTargetPhpToPlatformRepository(),
                );
            },
        );
        $container->bind(
            ClientInterface::class,
            static function (): ClientInterface {
                return new Client([RequestOptions::HTTP_ERRORS => false]);
            },
        );
        $container->singleton(
            AuthHelper::class,
            static function (ContainerInterface $container): AuthHelper {
                return new AuthHelper(
                    $container->get(IOInterface::class),
                    $container->get(Composer::class)->getConfig(),
                );
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
                    // @todo implement Windows installer
                    throw new RuntimeException('tbc');
                }

                return $container->get(UnixInstall::class);
            },
        );

        return $container;
    }
}
