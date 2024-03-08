<?php

declare(strict_types=1);

namespace Php\Pie;

use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Repository\CompositeRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryFactory;
use Composer\Repository\RepositorySet;
use Composer\Util\AuthHelper;
use Composer\Util\Platform;
use GuzzleHttp\Client;
use Php\Pie\Command\DownloadCommand;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\DependencyResolver\ResolveDependencyWithComposer;
use Php\Pie\Downloading\DownloadAndExtract;
use Php\Pie\Downloading\DownloadZip;
use Php\Pie\Downloading\ExtractZip;
use Php\Pie\Downloading\UnixDownloadAndExtract;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class Container
{
    public static function factory(): ContainerInterface
    {
        $container = new \Illuminate\Container\Container();
        $container->singleton(DownloadCommand::class);
        $container->singleton(
            DependencyResolver::class,
            static function (): DependencyResolver {
                $repositorySet = new RepositorySet();
                $repositorySet->addRepository(new CompositeRepository(RepositoryFactory::defaultReposWithDefaultManager(new NullIO())));

                return new ResolveDependencyWithComposer(
                    new PlatformRepository(),
                    $repositorySet,
                );
            },
        );
        $container->singleton(
            UnixDownloadAndExtract::class,
            static function (): UnixDownloadAndExtract {
                $config = Factory::createConfig();
                $io     = new NullIO();
                $io->loadConfiguration($config);

                return new UnixDownloadAndExtract(
                    new DownloadZip(
                        new Client(),
                        new AuthHelper($io, $config),
                    ),
                    new ExtractZip(),
                );
            },
        );
        $container->singleton(
            DownloadAndExtract::class,
            static function (ContainerInterface $container): DownloadAndExtract {
                if (Platform::isWindows()) {
                    // @todo add windows downloader
                    throw new RuntimeException('Windows support not yet');
                }

                return $container->get(UnixDownloadAndExtract::class);
            },
        );

        return $container;
    }
}
