<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\IO\IOInterface;
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
use Php\Pie\Platform\ThreadSafetyMode;
use Php\Pie\Util\Emoji;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_key_exists;
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

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            new PieComposerRequest(
                $this->io,
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
                $this->io,
                $targetPlatform,
                $this->container,
            );
        } catch (BundledPhpExtensionRefusal $bundledPhpExtensionRefusal) {
            $this->io->write('');
            $this->io->write('<comment>' . $bundledPhpExtensionRefusal->getMessage() . '</comment>');

            return self::INVALID;
        }

        $this->io->write(sprintf('<info>Found package:</info> %s which provides <info>%s</info>', $package->prettyNameAndVersion(), $package->extensionName()->nameWithExtPrefix()));

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
        $requires = $package->composerPackage()->getRequires();

        if (count($requires) > 0) {
            /** @var array<string, list<Constraint>> $platformConstraints */
            $platformConstraints = [];
            $composerPlatform    = new PhpBinaryPathBasedPlatformRepository($targetPlatform->phpBinaryPath, $composer, new InstalledPiePackages(), null);
            foreach ($composerPlatform->getPackages() as $platformPackage) {
                $platformConstraints[$platformPackage->getName()][] = new Constraint('==', $platformPackage->getVersion());
            }

            foreach ($requires as $requireName => $requireLink) {
                $packageStatus = sprintf('    %s: %s %%s', $requireName, $requireLink->getConstraint()->getPrettyString());
                if (! array_key_exists($requireName, $platformConstraints)) {
                    $this->io->write(sprintf($packageStatus, Emoji::PROHIBITED . ' (not installed)'));
                    continue;
                }

                foreach ($platformConstraints[$requireName] as $constraint) {
                    if ($requireLink->getConstraint()->matches($constraint)) {
                        $this->io->write(sprintf($packageStatus, Emoji::GREEN_CHECKMARK));
                    } else {
                        $this->io->write(sprintf($packageStatus, Emoji::PROHIBITED . ' (your version is ' . $constraint->getVersion() . ')'));
                    }
                }
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
