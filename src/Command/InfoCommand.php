<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\Semver\Constraint\Constraint;
use Php\Pie\ComposerIntegration\PhpBinaryPathBasedPlatformRepository;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\DependencyResolver\BundledPhpExtensionRefusal;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\DependencyResolver\InvalidPackageName;
use Php\Pie\DependencyResolver\UnableToResolveRequirement;
use Php\Pie\Installing\InstallForPhpProject\FindMatchingPackages;
use Php\Pie\Platform\InstalledPiePackages;
use Php\Pie\Util\Emoji;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_key_exists;
use function count;
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
        private readonly FindMatchingPackages $findMatchingPackages,
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

        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $output);

        try {
            $requestedNameAndVersion = CommandHelper::requestedNameAndVersionPair($input);
        } catch (InvalidPackageName $invalidPackageName) {
            return CommandHelper::handlePackageNotFound(
                $invalidPackageName,
                $this->findMatchingPackages,
                $output,
                $targetPlatform,
                $this->container,
            );
        }

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            new PieComposerRequest(
                $output,
                $targetPlatform,
                $requestedNameAndVersion,
                PieOperation::Resolve,
                [], // Configure options are not needed for resolve only
                null,
                false, // setting up INI not needed for info
            ),
        );

        try {
            $package = ($this->dependencyResolver)(
                $composer,
                $targetPlatform,
                $requestedNameAndVersion,
                true,
            );
        } catch (UnableToResolveRequirement $unableToResolveRequirement) {
            return CommandHelper::handlePackageNotFound(
                $unableToResolveRequirement,
                $this->findMatchingPackages,
                $output,
                $targetPlatform,
                $this->container,
            );
        } catch (BundledPhpExtensionRefusal $bundledPhpExtensionRefusal) {
            $output->writeln('');
            $output->writeln('<comment>' . $bundledPhpExtensionRefusal->getMessage() . '</comment>');

            return self::INVALID;
        }

        $output->writeln(sprintf('<info>Found package:</info> %s which provides <info>%s</info>', $package->prettyNameAndVersion(), $package->extensionName()->nameWithExtPrefix()));

        $output->writeln(sprintf('Extension name: %s', $package->extensionName()->name()));
        $output->writeln(sprintf('Extension type: %s (%s)', $package->extensionType()->value, $package->extensionType()->name));
        $output->writeln(sprintf('Composer package name: %s', $package->name()));
        $output->writeln(sprintf('Version: %s', $package->version()));
        $output->writeln(sprintf('Download URL: %s', $package->downloadUrl() ?? '(not specified)'));

        $output->writeln("\n<options=bold,underscore>Dependencies:</>");
        $requires = $package->composerPackage()->getRequires();

        if (count($requires) > 0) {
            /** @var array<string, list<Constraint>> $platformConstraints */
            $platformConstraints = [];
            $composerPlatform    = new PhpBinaryPathBasedPlatformRepository($targetPlatform->phpBinaryPath, $composer, new InstalledPiePackages(), null);
            foreach ($composerPlatform->getPackages() as $platformPackage) {
                $platformConstraints[$platformPackage->getName()][] = new Constraint('==', $platformPackage->getPrettyVersion());
            }

            foreach ($requires as $requireName => $requireLink) {
                $packageStatus = sprintf('    %s: %s %%s', $requireName, $requireLink->getConstraint()->getPrettyString());
                if (! array_key_exists($requireName, $platformConstraints)) {
                    $output->writeln(sprintf($packageStatus, Emoji::PROHIBITED . ' (not installed)'));
                    continue;
                }

                foreach ($platformConstraints[$requireName] as $constraint) {
                    if ($requireLink->getConstraint()->matches($constraint)) {
                        $output->writeln(sprintf($packageStatus, Emoji::GREEN_CHECKMARK));
                    } else {
                        $output->writeln(sprintf($packageStatus, Emoji::PROHIBITED . ' (your version is ' . $constraint->getVersion() . ')'));
                    }
                }
            }
        } else {
            $output->writeln('    No dependencies.');
        }

        $output->writeln("\n<options=bold,underscore>Configure options:</>");
        if (count($package->configureOptions())) {
            foreach ($package->configureOptions() as $configureOption) {
                $output->writeln(sprintf('    --%s%s  (%s)', $configureOption->name, $configureOption->needsValue ? '=?' : '', $configureOption->description));
            }
        } else {
            $output->writeln('    No configure options are specified.');
        }

        return Command::SUCCESS;
    }
}
