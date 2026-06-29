<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\IO\IOInterface;
use InvalidArgumentException;
use Php\Pie\ComposerIntegration\ComposerIntegrationHandler;
use Php\Pie\ComposerIntegration\ComposerRunFailed;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\DependencyResolver\BundledPhpExtensionRefusal;
use Php\Pie\DependencyResolver\DependencyInstaller\PrescanSystemDependencies;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\DependencyResolver\InvalidPackageName;
use Php\Pie\DependencyResolver\ResolvedPackageRequest;
use Php\Pie\DependencyResolver\UnableToResolveRequirement;
use Php\Pie\Installing\InstallForPhpProject\FindMatchingPackages;
use Php\Pie\Platform\PackageManager;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\SelfManage\BuildTools\CheckAllBuildTools;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'install',
    description: 'Download, build, and install a PIE-compatible PHP extension.',
)]
final class InstallCommand extends Command
{
    public const OPTION_FROM_LOCK = 'from-lock';

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly DependencyResolver $dependencyResolver,
        private readonly PrescanSystemDependencies $prescanSystemDependencies,
        private readonly ComposerIntegrationHandler $composerIntegrationHandler,
        private readonly InvokeSubCommand $invokeSubCommand,
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

        $this->addOption(
            self::OPTION_FROM_LOCK,
            null,
            InputOption::VALUE_NONE,
            'Install the exact versions specified in the pie.lock file for the target PHP.',
        );
    }

    public static function shouldInstallFromLock(InputInterface $input): bool
    {
        return $input->hasOption(self::OPTION_FROM_LOCK) && (bool) $input->getOption(self::OPTION_FROM_LOCK);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $installFromLock = self::shouldInstallFromLock($input);

        if ($installFromLock && $input->getArgument(CommandHelper::ARG_REQUESTED_PACKAGE_AND_VERSION)) {
            $this->io->writeError('<error>The --from-lock option installs all extensions from the lock file and cannot be combined with a specific package argument.</error>');

            return self::INVALID;
        }

        if (! $installFromLock && ! $input->getArgument(CommandHelper::ARG_REQUESTED_PACKAGE_AND_VERSION)) {
            return ($this->invokeSubCommand)(
                $this,
                ['command' => 'install-extensions-for-project'],
                $input,
            );
        }

        if (! TargetPlatform::isRunningAsRoot()) {
            $this->io->write('This command may need elevated privileges, and may prompt you for your password.');
        }

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
        } catch (InvalidArgumentException $noPackagesRequested) {
            if (! $installFromLock) {
                throw $noPackagesRequested;
            }

            $requestedNamesAndVersions = [];
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
                false, // setting up INI not needed for resolve step
            ),
        );

        if (CommandHelper::shouldCheckSystemDependencies($input)) {
            foreach ($requestedNamesAndVersions as $requestedNameAndVersion) {
                try {
                    ($this->prescanSystemDependencies)(
                        $composer,
                        $targetPlatform,
                        $requestedNameAndVersion,
                        CommandHelper::autoInstallSystemDependencies($input),
                    );
                } catch (Throwable $anything) {
                    $this->io->writeError(
                        '<comment>Skipping system dependency pre-scan due to exception:</comment> ' . $anything->getMessage(),
                        verbosity: IOInterface::VERBOSE,
                    );
                }
            }
        }

        $resolvedPackages = [];
        try {
            if ($requestedNamesAndVersions !== []) {
                $resolvedPackages = CommandHelper::resolveRequestedPackages(
                    $this->dependencyResolver,
                    $this->io,
                    $composer,
                    $targetPlatform,
                    $requestedNamesAndVersions,
                    $forceInstallPackageVersion,
                );
            }
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

        // Now we know what packages we have, we can validate the configure options for the command and re-create the
        // Composer instance with the populated configure options
        $resolvedPiePackages = ResolvedPackageRequest::piePackages($resolvedPackages);
        CommandHelper::bindConfigureOptionsFromPackage($this, $resolvedPiePackages, $input);
        $configureOptionsValues = CommandHelper::processConfigureOptionsFromInput($resolvedPiePackages, $input);

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            new PieComposerRequest(
                $this->io,
                $targetPlatform,
                $requestedNamesAndVersions,
                PieOperation::Install,
                $configureOptionsValues,
                CommandHelper::determineAttemptToSetupIniFile($input),
                installAllPackages: $installFromLock,
            ),
        );

        try {
            $this->composerIntegrationHandler->runInstall(
                $resolvedPackages,
                $composer,
                $targetPlatform,
                $forceInstallPackageVersion,
                true,
                installFromLock: $installFromLock,
            );
        } catch (ComposerRunFailed $composerRunFailed) {
            $this->io->writeError('<error>' . $composerRunFailed->getMessage() . '</error>');

            return $composerRunFailed->getCode();
        }

        return Command::SUCCESS;
    }
}
