<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\DependencyResolver\BundledPhpExtensionRefusal;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\DependencyResolver\ResolveDependencyWithComposer;
use Php\Pie\DependencyResolver\UnableToResolveRequirement;
use Php\Pie\Platform as PiePlatform;
use Php\Pie\Platform\InstalledPiePackages;
use Php\Pie\Util\Emoji;
use Php\Pie\Util\PackageVerificationStatus;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

use function array_diff;
use function array_map;
use function array_walk;
use function count;
use function rtrim;
use function sprintf;

#[AsCommand(
    name: 'show',
    description: 'List the installed modules and their versions.',
)]
final class ShowCommand extends Command
{
    private const OPTION_ALL = 'all';

    public function __construct(
        private readonly InstalledPiePackages $installedPiePackages,
        private readonly ContainerInterface $container,
        private readonly ResolveDependencyWithComposer $resolveDependencyWithComposer,
        private readonly IOInterface $io,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        parent::configure();

        CommandHelper::configurePhpConfigOptions($this);

        $this->addOption(
            self::OPTION_ALL,
            null,
            InputOption::VALUE_NONE,
            'Show all extensions for the target PHP installation, even those PIE does not manage.',
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $showAll        = $input->hasOption(self::OPTION_ALL) && $input->getOption(self::OPTION_ALL);
        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $this->io);

        $this->io->write(
            sprintf(
                '<info>Using pie.json:</info> %s',
                PiePlatform::getPieJsonFilename($targetPlatform),
            ),
            verbosity: IOInterface::VERBOSE,
        );

        if (! $showAll) {
            $this->io->write('Tip: to include extensions in this list that PIE does not manage, use the --all flag.');
        }

        CommandHelper::applyNoCacheOptionIfSet($input, $this->io);

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            PieComposerRequest::noOperation(
                new NullIO(),
                $targetPlatform,
            ),
        );

        $piePackages          = $this->installedPiePackages->allPiePackages($composer);
        $phpEnabledExtensions = $targetPlatform->phpBinaryPath->extensions();
        $piePackagesMatched   = [];
        $rootPackageRequires  = $composer->getPackage()->getRequires();

        $this->io->write(sprintf(
            "\n" . '<options=bold,underscore>%s:</>',
            $showAll ? 'All loaded extensions' : 'Loaded PIE extensions',
        ));
        array_walk(
            $phpEnabledExtensions,
            function (string $version, string $phpExtensionName) use ($composer, $rootPackageRequires, $targetPlatform, $showAll, $piePackages, &$piePackagesMatched): void {
                $pieMatchesForExtension = $piePackages->findByPhpFormattedExtensionName($phpExtensionName);

                if (! count($pieMatchesForExtension)) {
                    if ($showAll) {
                        $this->io->write(sprintf('  <comment>%s:%s</comment>', $phpExtensionName, $version));
                    }

                    return;
                }

                foreach ($pieMatchesForExtension->packages() as $piePackage) {
                    $packageName        = $piePackage->name();
                    $verificationStatus = $piePackage->verifyPackageStatus($targetPlatform);
                    $packageRequirement = $rootPackageRequires[$packageName]->getPrettyConstraint();

                    if ($verificationStatus === PackageVerificationStatus::InstalledBinaryMetadataMissing) {
                        continue;
                    }

                    $piePackagesMatched[] = $packageName;

                    try {
                        // Don't check for updates for bundled PHP extensions
                        if ($piePackage->isBundledPhpExtension()) {
                            throw new BundledPhpExtensionRefusal();
                        }

                        Assert::stringNotEmpty($packageName);
                        Assert::stringNotEmpty($packageRequirement);

                        $latestConstrainedPackage = ($this->resolveDependencyWithComposer)(
                            $composer,
                            $targetPlatform,
                            new RequestedPackageAndVersion($packageName, $packageRequirement),
                            false,
                        );

                        $latestPackage = ($this->resolveDependencyWithComposer)(
                            $composer,
                            $targetPlatform,
                            new RequestedPackageAndVersion($packageName, '*'),
                            false,
                        );
                    } catch (UnableToResolveRequirement | BundledPhpExtensionRefusal) {
                        $latestConstrainedPackage = null;
                        $latestPackage            = null;
                    }

                    $updateNotice = '';
                    if ($latestConstrainedPackage !== null && $latestConstrainedPackage->version() !== $piePackage->version()) {
                        $updateNotice = sprintf(
                            ', upgradable to %s (within %s)',
                            $latestConstrainedPackage->version(),
                            $packageRequirement,
                        );
                    }

                    if ($latestPackage !== null && $latestPackage->version() !== $latestConstrainedPackage->version()) {
                        $updateNotice .= sprintf(', latest version is %s', $latestPackage->version());
                    }

                    $this->io->write(sprintf(
                        '  <info>%s:%s</info> (from 🥧 <info>%s</info> %s)%s',
                        $phpExtensionName,
                        $version,
                        $piePackage->prettyNameAndVersion(),
                        $verificationStatus->description(),
                        $updateNotice,
                    ));
                }
            },
        );

        if (! $showAll && ! count($piePackagesMatched)) {
            $this->io->write('(none)');
        }

        $unmatchedPiePackageNames = array_diff(array_map(static fn (Package $piePackage) => $piePackage->name(), $piePackages->packages()), $piePackagesMatched);

        if (count($unmatchedPiePackageNames)) {
            $this->io->write(sprintf(
                '%s %s <options=bold,underscore>PIE packages not loaded:</>',
                "\n",
                Emoji::WARNING,
            ));
            $this->io->write('These extensions were set up with PIE but are not currently enabled.' . "\n");

            foreach ($unmatchedPiePackageNames as $unmatchedPiePackageName) {
                $unmatchedPiePackage = $piePackages->findByPackageName($unmatchedPiePackageName);

                $message = match ($unmatchedPiePackage->verifyPackageStatus($targetPlatform)) {
                    PackageVerificationStatus::ChecksumMetadataMissing => '- was built but not installed yet.',
                    PackageVerificationStatus::InstalledBinaryMetadataMissing => '- was downloaded but has not been built yet.',
                    default => '- installed but not enabled in INI file',
                };
                $this->io->write(rtrim(sprintf(' - %s %s', $unmatchedPiePackage->prettyNameAndVersion(), $message)));
            }
        }

        return Command::SUCCESS;
    }
}
