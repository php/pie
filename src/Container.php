<?php

declare(strict_types=1);

namespace Php\Pie;

use Composer\Composer;
use Composer\Factory as ComposerFactory;
use Composer\IO\ConsoleIO;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Repository\CompositeRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryFactory;
use Composer\Repository\RepositorySet;
use Composer\Util\AuthHelper;
use Composer\Util\Platform;
use GuzzleHttp\Client;
use Illuminate\Container\Container as IlluminateContainer;
use Php\Pie\Command\DownloadCommand;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\DependencyResolver\ResolveDependencyWithComposer;
use Php\Pie\Downloading\DownloadAndExtract;
use Php\Pie\Downloading\DownloadZip;
use Php\Pie\Downloading\ExtractZip;
use Php\Pie\Downloading\UnixDownloadAndExtract;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class Container
{
    public static function factory(): ContainerInterface
    {
        $container = new IlluminateContainer();
        $container->instance(InputInterface::class, new ArgvInput());
        $container->instance(OutputInterface::class, new ConsoleOutput());

        $container->singleton(DownloadCommand::class);

        $container->singleton(IOInterface::class, static function (ContainerInterface $container): IOInterface {
            return new ConsoleIO(
                $container->get(InputInterface::class),
                $container->get(OutputInterface::class),
                new HelperSet([]),
            );
        });
        $container->singleton(Composer::class, static function (ContainerInterface $container): Composer {
            $io       = $container->get(IOInterface::class);
            $composer = ComposerFactory::create($io);
            $io->loadConfiguration($composer->getConfig());

            return $composer;
        });

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
            static function (ContainerInterface $container): UnixDownloadAndExtract {
                return new UnixDownloadAndExtract(
                    new DownloadZip(
                        new Client(),
                        new AuthHelper(
                            $container->get(IOInterface::class),
                            $container->get(Composer::class)->getConfig(),
                        ),
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
