<?php

declare(strict_types=1);

namespace Php\Pie;

use Composer\Util\Platform as ComposerPlatform;
use Illuminate\Container\Container as IlluminateContainer;
use Php\Pie\Building\Build;
use Php\Pie\Building\UnixBuild;
use Php\Pie\Building\WindowsBuild;
use Php\Pie\Command\BuildCommand;
use Php\Pie\Command\DownloadCommand;
use Php\Pie\Command\InfoCommand;
use Php\Pie\Command\InstallCommand;
use Php\Pie\Command\InstallExtensionsForProjectCommand;
use Php\Pie\Command\RepositoryAddCommand;
use Php\Pie\Command\RepositoryListCommand;
use Php\Pie\Command\RepositoryRemoveCommand;
use Php\Pie\Command\SelfUpdateCommand;
use Php\Pie\Command\SelfVerifyCommand;
use Php\Pie\Command\ShowCommand;
use Php\Pie\Command\UninstallCommand;
use Php\Pie\ComposerIntegration\MinimalHelperSet;
use Php\Pie\ComposerIntegration\QuieterConsoleIO;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\DependencyResolver\ResolveDependencyWithComposer;
use Php\Pie\Downloading\GithubPackageReleaseAssets;
use Php\Pie\Downloading\PackageReleaseAssets;
use Php\Pie\File\FullPathToSelf;
use Php\Pie\Installing\Ini;
use Php\Pie\Installing\Install;
use Php\Pie\Installing\Uninstall;
use Php\Pie\Installing\UninstallUsingUnlink;
use Php\Pie\Installing\UnixInstall;
use Php\Pie\Installing\WindowsInstall;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function defined;
use function fopen;
use function getcwd;
use function str_starts_with;

use const STDIN;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class Container
{
    public static function factory(): ContainerInterface
    {
        $container = new IlluminateContainer();
        $container->instance(ContainerInterface::class, $container);
        $container->singleton(
            InputInterface::class,
            static function () {
                $input = new ArgvInput();

                $stdin            = defined('STDIN') ? STDIN : fopen('php://stdin', 'r');
                $noInteractionEnv = ComposerPlatform::getEnv('COMPOSER_NO_INTERACTION');
                if (
                    $noInteractionEnv === false
                    || $noInteractionEnv === '1'
                    || $stdin === false
                    || ! ComposerPlatform::isTty($stdin)
                ) {
                    $input->setInteractive(false);
                }

                return $input;
            },
        );
        $container->instance(OutputInterface::class, new ConsoleOutput());
        $container->singleton(EventDispatcher::class, static function () {
            $displayedBanner = false;
            $eventDispatcher = new EventDispatcher();
            $eventDispatcher->addListener(
                ConsoleEvents::COMMAND,
                static function (ConsoleCommandEvent $event) use (&$displayedBanner): void {
                    $command     = $event->getCommand();
                    $application = $command?->getApplication();

                    if ($displayedBanner || $command === null || ! str_starts_with($command::class, 'Php\Pie\Command') || $application === null) {
                        return;
                    }

                    $displayedBanner = true;
                    $event->getOutput()->writeln($application->getLongVersion() . ', from The PHP Foundation');
                },
            );

            return $eventDispatcher;
        });

        $container->singleton(DownloadCommand::class);
        $container->singleton(BuildCommand::class);
        $container->singleton(InstallCommand::class);
        $container->singleton(InfoCommand::class);
        $container->singleton(ShowCommand::class);
        $container->singleton(RepositoryListCommand::class);
        $container->singleton(RepositoryAddCommand::class);
        $container->singleton(RepositoryRemoveCommand::class);
        $container->singleton(UninstallCommand::class);
        $container->singleton(SelfUpdateCommand::class);
        $container->singleton(SelfVerifyCommand::class);
        $container->singleton(InstallExtensionsForProjectCommand::class);

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

        $container->when(SelfUpdateCommand::class)
            ->needs('$githubApiBaseUrl')
            ->give('https://api.github.com');

        $container->when(SelfVerifyCommand::class)
            ->needs('$githubApiBaseUrl')
            ->give('https://api.github.com');

        $container->when(FullPathToSelf::class)
            ->needs('$originalCwd')
            ->give(getcwd());

        $container->singleton(
            Build::class,
            static function (ContainerInterface $container): Build {
                if (ComposerPlatform::isWindows()) {
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
                if (ComposerPlatform::isWindows()) {
                    return $container->get(WindowsInstall::class);
                }

                return $container->get(UnixInstall::class);
            },
        );

        $container->alias(UninstallUsingUnlink::class, Uninstall::class);

        $container->alias(Ini\RemoveIniEntryWithFileGetContents::class, Ini\RemoveIniEntry::class);

        return $container;
    }
}
