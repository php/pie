<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Php\Pie\ComposerIntegration\ComposerIntegrationHandler;
use Php\Pie\ComposerIntegration\ComposerRunFailed;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\DependencyResolver\DependencyResolver;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

#[AsCommand(
    name: 'build',
    description: 'Download and build a PIE-compatible PHP extension, without installing it.',
)]
final class BuildCommand extends Command
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly DependencyResolver $dependencyResolver,
        private readonly ComposerIntegrationHandler $composerIntegrationHandler,
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
        $targetPlatform             = CommandHelper::determineTargetPlatformFromInputs($input, $output);
        $requestedNameAndVersion    = CommandHelper::requestedNameAndVersionPair($input);
        $forceInstallPackageVersion = CommandHelper::determineForceInstallingPackageVersion($input);

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            new PieComposerRequest(
                $output,
                $targetPlatform,
                $requestedNameAndVersion,
                PieOperation::Resolve,
                [], // Configure options are not needed for resolve only
                null,
                false, // setting up INI not needed for build
            ),
        );

        $package = ($this->dependencyResolver)(
            $composer,
            $targetPlatform,
            $requestedNameAndVersion,
            $forceInstallPackageVersion,
        );
        $output->writeln(sprintf('<info>Found package:</info> %s which provides <info>%s</info>', $package->prettyNameAndVersion(), $package->extensionName->nameWithExtPrefix()));

        // Now we know what package we have, we can validate the configure options for the command and re-create the
        // Composer instance with the populated configure options
        CommandHelper::bindConfigureOptionsFromPackage($this, $package, $input);
        $configureOptionsValues = CommandHelper::processConfigureOptionsFromInput($package, $input);

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            new PieComposerRequest(
                $output,
                $targetPlatform,
                $requestedNameAndVersion,
                PieOperation::Build,
                $configureOptionsValues,
                CommandHelper::determinePhpizePathFromInputs($input),
                false, // setting up INI not needed for build
            ),
        );

        try {
            ($this->composerIntegrationHandler)(
                $package,
                $composer,
                $targetPlatform,
                $requestedNameAndVersion,
                $forceInstallPackageVersion,
            );
        } catch (ComposerRunFailed $composerRunFailed) {
            $output->writeln('<error>' . $composerRunFailed->getMessage() . '</error>');

            return $composerRunFailed->getCode();
        }

        return Command::SUCCESS;
    }
}
