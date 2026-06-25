<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\IO\IOInterface;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\DependencyResolver\BundledPhpExtensionRefusal;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\DependencyResolver\FetchDependencyStatuses;
use Php\Pie\DependencyResolver\InvalidPackageName;
use Php\Pie\DependencyResolver\UnableToResolveRequirement;
use Php\Pie\Installing\InstallForPhpProject\FindMatchingPackages;
use Php\Pie\Platform\ThreadSafetyMode;
use Php\Pie\Util\Emoji;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function in_array;
use function sprintf;

#[AsCommand(
    name: 'info',
    description: 'Show metadata about a given extension.',
)]
final class InfoCommand extends Command
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly DependencyResolver $dependencyResolver,
        private readonly FetchDependencyStatuses $fetchDependencyStatuses,
        private readonly FindMatchingPackages $findMatchingPackages,
        private readonly IOInterface $io,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        parent::configure();

        // `info` only ever supports a single package, unlike the other download/build/install commands.
        CommandHelper::configureDownloadBuildInstallOptions($this, false);
        $this->addArgument(
            CommandHelper::ARG_REQUESTED_PACKAGE_AND_VERSION,
            InputArgument::REQUIRED,
            'The PIE package name and version constraint to use, in the format {vendor/package}{?:{?version-constraint}{?@stability}}, for example `xdebug/xdebug:^3.4@alpha`, `xdebug/xdebug:@alpha`, `xdebug/xdebug:^3.4`, etc.',
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        CommandHelper::validateInput($input, $this);

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

        CommandHelper::applyNoCacheOptionIfSet($input, $this->io);

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            new PieComposerRequest(
                $this->io,
                $targetPlatform,
                $requestedNamesAndVersions,
                PieOperation::Resolve,
                [], // Configure options are not needed for resolve only
                false, // setting up INI not needed for info
            ),
        );

        try {
            $resolvedPackages = CommandHelper::resolveRequestedPackages(
                $this->dependencyResolver,
                $this->io,
                $composer,
                $targetPlatform,
                $requestedNamesAndVersions,
                true,
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

        $package = $resolvedPackages[0]->piePackage;

        $this->io->write(sprintf('Extension name: %s', $package->extensionName()->name()));
        $this->io->write(sprintf('Extension type: %s (%s)', $package->extensionType()->value, $package->extensionType()->name));
        $this->io->write(sprintf('Composer package name: %s', $package->name()));
        $this->io->write(sprintf('Version: %s', $package->version()));
        $this->io->write(sprintf('Download URL: %s', $package->downloadUrl() ?? '(not specified)'));
        $this->io->write(sprintf(
            'TS/NTS: %s',
            ($targetPlatform->threadSafety === ThreadSafetyMode::NonThreadSafe && ! $package->supportNts())
                || ($targetPlatform->threadSafety === ThreadSafetyMode::ThreadSafe && ! $package->supportZts()) ? sprintf('%s (not supported on %s)', Emoji::PROHIBITED, $targetPlatform->threadSafety->asShort()) : Emoji::GREEN_CHECKMARK,
        ));

        $this->io->write(sprintf(
            'OS: %s',
            ($package->compatibleOsFamilies() === null || in_array($targetPlatform->operatingSystemFamily, $package->compatibleOsFamilies(), true))
            && ($package->incompatibleOsFamilies() === null || ! in_array($targetPlatform->operatingSystemFamily, $package->incompatibleOsFamilies(), true))
                ? Emoji::GREEN_CHECKMARK
                : sprintf('%s (not supported on %s)', Emoji::PROHIBITED, $targetPlatform->operatingSystemFamily->value),
        ));

        $this->io->write("\n<options=bold,underscore>Dependencies:</>");

        $dependencyStatuses = ($this->fetchDependencyStatuses)($targetPlatform, $composer, $package->composerPackage());
        if (count($dependencyStatuses) > 0) {
            foreach ($dependencyStatuses as $dependencyStatus) {
                $this->io->write('    ' . $dependencyStatus->asPrettyString());
            }
        } else {
            $this->io->write('    No dependencies.');
        }

        $this->io->write("\n<options=bold,underscore>Configure options:</>");
        if (count($package->configureOptions())) {
            foreach ($package->configureOptions() as $configureOption) {
                $this->io->write(sprintf('    --%s%s  (%s)', $configureOption->name, $configureOption->needsValue ? '=?' : '', $configureOption->description));
            }
        } else {
            $this->io->write('    No configure options are specified.');
        }

        return Command::SUCCESS;
    }
}
