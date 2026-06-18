<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\IO\IOInterface;
use Php\Pie\ComposerIntegration\ComposerIntegrationHandler;
use Php\Pie\ComposerIntegration\ComposerRunFailed;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\DependencyResolver\BundledPhpExtensionRefusal;
use Php\Pie\DependencyResolver\DependencyInstaller\PrescanSystemDependencies;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\DependencyResolver\InvalidPackageName;
use Php\Pie\DependencyResolver\UnableToResolveRequirement;
use Php\Pie\Installing\InstallForPhpProject\FindMatchingPackages;
use Php\Pie\Platform\PackageManager;
use Php\Pie\SelfManage\BuildTools\CheckAllBuildTools;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'build',
    description: 'Download and build a PIE-compatible PHP extension, without installing it.',
)]
final class BuildCommand extends Command
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly DependencyResolver $dependencyResolver,
        private readonly PrescanSystemDependencies $prescanSystemDependencies,
        private readonly ComposerIntegrationHandler $composerIntegrationHandler,
        private readonly FindMatchingPackages $findMatchingPackages,
        private readonly IOInterface $io,
        private readonly CheckAllBuildTools $checkBuildTools,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        parent::configure();

        CommandHelper::configureDownloadBuildInstallOptions($this);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $this->io);
        try {
            $requestedNamesAndVersions = CommandHelper::requestedNameAndVersionPairs($input);
        } catch (InvalidPackageName $invalidPackageName) {
            return CommandHelper::handlePackageNotFound(
                $invalidPackageName,
                $this->findMatchingPackages,
                $this->io,
                $targetPlatform,
                $this->container,
            );
        }

        $forceInstallPackageVersion = CommandHelper::determineForceInstallingPackageVersion($input);
        CommandHelper::applyNoCacheOptionIfSet($input, $this->io);

        if (CommandHelper::shouldCheckForBuildTools($input)) {
            $this->checkBuildTools->check(
                $this->io,
                PackageManager::detect(),
                $targetPlatform,
                CommandHelper::autoInstallBuildTools($input),
            );
        }

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            new PieComposerRequest(
                $this->io,
                $targetPlatform,
                $requestedNamesAndVersions,
                PieOperation::Resolve,
                [], // Configure options are not needed for resolve only
                false, // setting up INI not needed for build
            ),
        );

        // @todo fix this
//        if (CommandHelper::shouldCheckSystemDependencies($input)) {
//            try {
//                ($this->prescanSystemDependencies)(
//                    $composer,
//                    $targetPlatform,
//                    $requestedNamesAndVersions,
//                    CommandHelper::autoInstallSystemDependencies($input),
//                );
//            } catch (Throwable $anything) {
//                $this->io->writeError(
//                    '<comment>Skipping system dependency pre-scan due to exception:</comment> ' . $anything->getMessage(),
//                    verbosity: IOInterface::VERBOSE,
//                );
//            }
//        }

        try {
            $resolvedPackages = CommandHelper::resolveRequestedPackages(
                $this->dependencyResolver,
                $this->io,
                $composer,
                $targetPlatform,
                $requestedNamesAndVersions,
                $forceInstallPackageVersion,
            );
        } catch (UnableToResolveRequirement $unableToResolveRequirement) {
            return CommandHelper::handlePackageNotFound(
                $unableToResolveRequirement,
                $this->findMatchingPackages,
                $this->io,
                $targetPlatform,
                $this->container,
            );
        } catch (BundledPhpExtensionRefusal $bundledPhpExtensionRefusal) {
            $this->io->writeError('');
            $this->io->writeError('<comment>' . $bundledPhpExtensionRefusal->getMessage() . '</comment>');

            return self::INVALID;
        }

        // Now we know what package we have, we can validate the configure options for the command and re-create the
        // Composer instance with the populated configure options
        // @todo handle this; CommandHelper::bindConfigureOptionsFromPackage($this, $package, $input);
        // @todo handle this; $configureOptionsValues = CommandHelper::processConfigureOptionsFromInput($package, $input);
        $configureOptionsValues = []; // @todo handle this

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            new PieComposerRequest(
                $this->io,
                $targetPlatform,
                $requestedNamesAndVersions,
                PieOperation::Build,
                $configureOptionsValues,
                false, // setting up INI not needed for build
            ),
        );

        try {
            $this->composerIntegrationHandler->runInstall(
                $resolvedPackages,
                $composer,
                $targetPlatform,
                $forceInstallPackageVersion,
                false,
            );
        } catch (ComposerRunFailed $composerRunFailed) {
            $this->io->writeError('<error>' . $composerRunFailed->getMessage() . '</error>');

            return $composerRunFailed->getCode();
        }

        return Command::SUCCESS;
    }
}
