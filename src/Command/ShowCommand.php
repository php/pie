<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\Package\BasePackage;
use Composer\Package\CompletePackageInterface;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Platform\TargetPlatform;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_combine;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_walk;
use function sprintf;

#[AsCommand(
    name: 'show',
    description: 'List the installed modules and their versions.',
)]
final class ShowCommand extends Command
{
    public function __construct(
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

        $piePackages          = $this->buildListOfPieInstalledPackages($output, $targetPlatform);
        $phpEnabledExtensions = $targetPlatform->phpBinaryPath->extensions();

        $output->writeln("\n" . '<info>Loaded extensions:</info>');
        array_walk(
            $phpEnabledExtensions,
            static function (string $version, string $phpExtensionName) use ($output, $piePackages): void {
                if (! array_key_exists($phpExtensionName, $piePackages)) {
                    $output->writeln(sprintf('  <comment>%s:%s</comment>', $phpExtensionName, $version));

                    return;
                }

                // @todo determine if installed ext has drifted using the PIE checksum

                $piePackage = $piePackages[$phpExtensionName];
                $output->writeln(sprintf(
                    '  <info>%s:%s</info> (from <info>%s</info>)',
                    $phpExtensionName,
                    $version,
                    $piePackage->prettyNameAndVersion(),
                ));
            },
        );

        return Command::SUCCESS;
    }

    /** @return array<non-empty-string, Package> */
    private function buildListOfPieInstalledPackages(
        OutputInterface $output,
        TargetPlatform $targetPlatform,
    ): array {
        $composerInstalledPackages = array_map(
            static function (CompletePackageInterface $package): Package {
                return Package::fromComposerCompletePackage($package);
            },
            array_filter(
                PieComposerFactory::createPieComposer(
                    $this->container,
                    PieComposerRequest::noOperation(
                        $output,
                        $targetPlatform,
                    ),
                )
                    ->getRepositoryManager()
                    ->getLocalRepository()
                    ->getPackages(),
                static function (BasePackage $basePackage): bool {
                    return $basePackage instanceof CompletePackageInterface;
                },
            ),
        );

        return array_combine(
            array_map(
            /** @return non-empty-string */
                static function (Package $package): string {
                    return $package->extensionName->name();
                },
                $composerInstalledPackages,
            ),
            $composerInstalledPackages,
        );
    }
}
