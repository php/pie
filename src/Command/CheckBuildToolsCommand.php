<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\IO\IOInterface;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\PackageManager;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\SelfManage\BuildTools\CheckAllBuildTools;
use Php\Pie\Util\Emoji;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

#[AsCommand(
    name: 'check-build-tools',
    description: 'Check that all build tools required to compile PHP extensions are installed.',
)]
final class CheckBuildToolsCommand extends Command
{
    public function __construct(
        private readonly CheckAllBuildTools $checkAllBuildTools,
        private readonly PackageManager|null $packageManager,
        private readonly IOInterface $io,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        parent::configure();

        CommandHelper::configurePhpConfigOptions($this);
        CommandHelper::configureBuildToolsCheckOptions($this);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $this->io);

        if ($targetPlatform->operatingSystem === OperatingSystem::Windows) {
            $this->io->writeError('Build tools are not required on Windows systems!');

            return 0;
        }

        $statuses = $this->checkAllBuildTools->statuses($targetPlatform, $this->packageManager);

        $this->io->write("\n" . '<options=bold,underscore>Build tools typically required to build extensions:</>');

        $anyMissing = false;
        foreach ($statuses as $status) {
            if ($status->found) {
                $this->io->write(sprintf('  %s %s', Emoji::GREEN_CHECKMARK, $status->toolNames));
                continue;
            }

            $anyMissing = true;
            $this->io->write(sprintf(
                '  %s %s%s',
                Emoji::CROSS,
                $status->toolNames,
                $status->packageName !== null ? sprintf(' (installs with package: %s)', $status->packageName) : '',
            ));
        }

        if (! $anyMissing) {
            $this->io->write(sprintf("\n" . '<info>%s All build tools are installed.</info>', Emoji::GREEN_CHECKMARK));

            return Command::SUCCESS;
        }

        $this->io->write('');

        $this->checkAllBuildTools->check(
            $this->io,
            $this->packageManager,
            $targetPlatform,
            CommandHelper::autoInstallBuildTools($input),
        );

        // Check again things got installed as expected
        if (! $this->allToolsFound($targetPlatform)) {
            $this->io->writeError(sprintf("\n" . '<error>%s Some build tools are still missing.</error>', Emoji::CROSS));

            return Command::FAILURE;
        }

        $this->io->write(sprintf("\n" . '<info>%s All build tools are now installed.</info>', Emoji::GREEN_CHECKMARK));

        return Command::SUCCESS;
    }

    private function allToolsFound(TargetPlatform $targetPlatform): bool
    {
        foreach ($this->checkAllBuildTools->statuses($targetPlatform, $this->packageManager) as $status) {
            if (! $status->found) {
                return false;
            }
        }

        return true;
    }
}
