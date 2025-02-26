<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieInstalledJsonMetadataKeys;
use Php\Pie\File\BinaryFile;
use Php\Pie\File\BinaryFileFailedVerification;
use Php\Pie\Platform\InstalledPiePackages;
use Php\Pie\Platform\OperatingSystem;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

use function array_key_exists;
use function array_walk;
use function file_exists;
use function sprintf;
use function substr;

use const DIRECTORY_SEPARATOR;

/** @psalm-import-type PieMetadata from PieInstalledJsonMetadataKeys */
#[AsCommand(
    name: 'show',
    description: 'List the installed modules and their versions.',
)]
final class ShowCommand extends Command
{
    public function __construct(
        private readonly InstalledPiePackages $installedPiePackages,
        private readonly ContainerInterface $container,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        parent::configure();

        CommandHelper::configurePhpConfigOptions($this);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $output);

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            PieComposerRequest::noOperation(
                new NullOutput(),
                $targetPlatform,
            ),
        );

        $piePackages          = $this->installedPiePackages->allPiePackages($composer);
        $phpEnabledExtensions = $targetPlatform->phpBinaryPath->extensions();
        $extensionPath        = $targetPlatform->phpBinaryPath->extensionPath();
        $extensionEnding      = $targetPlatform->operatingSystem === OperatingSystem::Windows ? '.dll' : '.so';

        $output->writeln("\n" . '<info>Loaded extensions:</info>');
        array_walk(
            $phpEnabledExtensions,
            static function (string $version, string $phpExtensionName) use ($output, $piePackages, $extensionPath, $extensionEnding): void {
                if (! array_key_exists($phpExtensionName, $piePackages)) {
                    $output->writeln(sprintf('  <comment>%s:%s</comment>', $phpExtensionName, $version));

                    return;
                }

                $piePackage = $piePackages[$phpExtensionName];

                $output->writeln(sprintf(
                    '  <info>%s:%s</info> (from 🥧 <info>%s</info>%s)',
                    $phpExtensionName,
                    $version,
                    $piePackage->prettyNameAndVersion(),
                    self::verifyChecksumInformation(
                        $extensionPath,
                        $phpExtensionName,
                        $extensionEnding,
                        PieInstalledJsonMetadataKeys::pieMetadataFromComposerPackage($piePackage->composerPackage()),
                    ),
                ));
            },
        );

        return Command::SUCCESS;
    }

    /**
     * @param PieMetadata $installedJsonMetadata
     * @psalm-param '.dll'|'.so' $extensionEnding
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
            return ' ⚠️ was ' . substr($actualBinaryFile->checksum, 0, 8) . '..., expected ' . substr($expectedBinaryFileFromMetadata->checksum, 0, 8) . '...';
        }

        return ' ✅';
    }
}
