<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Php\Pie\BinaryFile;
use Php\Pie\ComposerIntegration\PieInstalledJsonMetadataKeys;
use Php\Pie\Platform\InstalledPiePackages;
use Php\Pie\Platform\OperatingSystem;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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

        $piePackages          = $this->installedPiePackages->allPiePackages($targetPlatform);
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
        $expectedConventionalBinaryPath = $extensionPath . DIRECTORY_SEPARATOR . $phpExtensionName . $extensionEnding;

        // The extension may not be in the usual path (since you can specify a full path to an extension in the INI file)
        if (! file_exists($expectedConventionalBinaryPath)) {
            return '';
        }

        $pieExpectedBinaryPath = array_key_exists(PieInstalledJsonMetadataKeys::InstalledBinary->value, $installedJsonMetadata) ? $installedJsonMetadata[PieInstalledJsonMetadataKeys::InstalledBinary->value] : null;
        $pieExpectedChecksum   = array_key_exists(PieInstalledJsonMetadataKeys::BinaryChecksum->value, $installedJsonMetadata) ? $installedJsonMetadata[PieInstalledJsonMetadataKeys::BinaryChecksum->value] : null;

        // Some other kind of mismatch of file path, or we don't have a stored checksum available
        if ($expectedConventionalBinaryPath !== $pieExpectedBinaryPath || $pieExpectedChecksum === null) {
            return '';
        }

        $actualInstalledBinary = BinaryFile::fromFileWithSha256Checksum($expectedConventionalBinaryPath);
        if ($actualInstalledBinary->checksum !== $pieExpectedChecksum) {
            return ' ⚠️ was ' . substr($actualInstalledBinary->checksum, 0, 8) . '..., expected ' . substr($pieExpectedChecksum, 0, 8) . '...';
        }

        return ' ✅';
    }
}
