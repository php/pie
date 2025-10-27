<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieInstalledJsonMetadataKeys;
use Php\Pie\DependencyResolver\BundledPhpExtensionRefusal;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\DependencyResolver\ResolveDependencyWithComposer;
use Php\Pie\DependencyResolver\UnableToResolveRequirement;
use Php\Pie\File\BinaryFile;
use Php\Pie\File\BinaryFileFailedVerification;
use Php\Pie\Platform as PiePlatform;
use Php\Pie\Platform\InstalledPiePackages;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Util\Emoji;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_walk;
use function count;
use function file_exists;
use function sprintf;
use function substr;

use const DIRECTORY_SEPARATOR;

/** @phpstan-import-type PieMetadata from PieInstalledJsonMetadataKeys */
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
        $extensionPath        = $targetPlatform->phpBinaryPath->extensionPath();
        $extensionEnding      = $targetPlatform->operatingSystem === OperatingSystem::Windows ? '.dll' : '.so';
        $piePackagesMatched   = [];
        $rootPackageRequires  = $composer->getPackage()->getRequires();

        $this->io->write(sprintf(
            "\n" . '<options=bold,underscore>%s:</>',
            $showAll ? 'All loaded extensions' : 'Loaded PIE extensions',
        ));
        array_walk(
            $phpEnabledExtensions,
            function (string $version, string $phpExtensionName) use ($composer, $rootPackageRequires, $targetPlatform, $showAll, $piePackages, $extensionPath, $extensionEnding, &$piePackagesMatched): void {
                if (! array_key_exists($phpExtensionName, $piePackages)) {
                    if ($showAll) {
                        $this->io->write(sprintf('  <comment>%s:%s</comment>', $phpExtensionName, $version));
                    }

                    return;
                }

                $piePackage           = $piePackages[$phpExtensionName];
                $piePackagesMatched[] = $phpExtensionName;
                $packageName          = $piePackage->name();
                $packageRequirement   = $rootPackageRequires[$piePackage->name()]->getPrettyConstraint();

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
                    '  <info>%s:%s</info> (from 🥧 <info>%s</info>%s)%s',
                    $phpExtensionName,
                    $version,
                    $piePackage->prettyNameAndVersion(),
                    self::verifyChecksumInformation(
                        $extensionPath,
                        $phpExtensionName,
                        $extensionEnding,
                        PieInstalledJsonMetadataKeys::pieMetadataFromComposerPackage($piePackage->composerPackage()),
                    ),
                    $updateNotice,
                ));
            },
        );

        if (! $showAll && ! count($piePackagesMatched)) {
            $this->io->write('(none)');
        }

        $unmatchedPiePackages = array_diff(array_keys($piePackages), $piePackagesMatched);

        if (count($unmatchedPiePackages)) {
            $this->io->write(sprintf(
                '%s %s <options=bold,underscore>PIE packages not loaded:</>',
                "\n",
                Emoji::WARNING,
            ));
            $this->io->write('These extensions were installed with PIE but are not currently enabled.' . "\n");

            foreach ($unmatchedPiePackages as $unmatchedPiePackage) {
                $this->io->write(sprintf(' - %s', $piePackages[$unmatchedPiePackage]->prettyNameAndVersion()));
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param PieMetadata $installedJsonMetadata
     * @phpstan-param '.dll'|'.so' $extensionEnding
     */
    private static function verifyChecksumInformation(
        string $extensionPath,
        string $phpExtensionName,
        string $extensionEnding,
        array $installedJsonMetadata,
    ): string {
        $actualBinaryPathByConvention = $extensionPath . DIRECTORY_SEPARATOR . $phpExtensionName . $extensionEnding;

        // The extension may not be in the usual path (since you can specify a full path to an extension in the INI file)
        if (! file_exists($actualBinaryPathByConvention)) {
            return '';
        }

        $pieExpectedBinaryPath = array_key_exists(PieInstalledJsonMetadataKeys::InstalledBinary->value, $installedJsonMetadata) ? $installedJsonMetadata[PieInstalledJsonMetadataKeys::InstalledBinary->value] : null;
        $pieExpectedChecksum   = array_key_exists(PieInstalledJsonMetadataKeys::BinaryChecksum->value, $installedJsonMetadata) ? $installedJsonMetadata[PieInstalledJsonMetadataKeys::BinaryChecksum->value] : null;

        // Some other kind of mismatch of file path, or we don't have a stored checksum available
        if (
            $pieExpectedBinaryPath === null
            || $pieExpectedChecksum === null
            || $pieExpectedBinaryPath !== $actualBinaryPathByConvention
        ) {
            return '';
        }

        $expectedBinaryFileFromMetadata = new BinaryFile($pieExpectedBinaryPath, $pieExpectedChecksum);
        $actualBinaryFile               = BinaryFile::fromFileWithSha256Checksum($actualBinaryPathByConvention);

        try {
            $expectedBinaryFileFromMetadata->verifyAgainstOther($actualBinaryFile);
        } catch (BinaryFileFailedVerification) {
            return sprintf(
                ' %s was %s..., expected %s...',
                Emoji::WARNING,
                substr($actualBinaryFile->checksum, 0, 8),
                substr($expectedBinaryFileFromMetadata->checksum, 0, 8),
            );
        }

        return ' ' . Emoji::GREEN_CHECKMARK;
    }
}
