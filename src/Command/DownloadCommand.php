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
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\DependencyResolver\InvalidPackageName;
use Php\Pie\DependencyResolver\UnableToResolveRequirement;
use Php\Pie\Installing\InstallForPhpProject\FindMatchingPackages;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

#[AsCommand(
    name: 'download',
    description: 'Same behaviour as build, but puts the files in a local directory for manual building and installation.',
)]
final class DownloadCommand extends Command
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly DependencyResolver $dependencyResolver,
        private readonly ComposerIntegrationHandler $composerIntegrationHandler,
        private readonly FindMatchingPackages $findMatchingPackages,
        private readonly IOInterface $io,
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
        CommandHelper::validateInput($input, $this);

        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $this->io);
        try {
            $requestedNameAndVersion = CommandHelper::requestedNameAndVersionPair($input);
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

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            new PieComposerRequest(
                $this->io,
                $targetPlatform,
                $requestedNameAndVersion,
                PieOperation::Download,
                [], // Configure options are not needed for download only
                null,
                false, // setting up INI not needed for download
            ),
        );

        try {
            $package = ($this->dependencyResolver)(
                $composer,
                $targetPlatform,
                $requestedNameAndVersion,
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

        $this->io->write(sprintf('<info>Found package:</info> %s which provides <info>%s</info>', $package->prettyNameAndVersion(), $package->extensionName()->nameWithExtPrefix()));

        try {
            $this->composerIntegrationHandler->runInstall(
                $package,
                $composer,
                $targetPlatform,
                $requestedNameAndVersion,
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
