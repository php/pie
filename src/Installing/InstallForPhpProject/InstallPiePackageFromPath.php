<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Composer\Package\RootPackageInterface;
use Php\Pie\Command\InvokeSubCommand;
use Php\Pie\ComposerIntegration\PieJsonEditor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class InstallPiePackageFromPath
{
    /** @psalm-suppress PossiblyUnusedMethod no direct reference; used in service locator */
    public function __construct(private readonly InvokeSubCommand $invokeSubCommand)
    {
    }

    /** @param non-empty-string $piePackagePath */
    public function __invoke(Command $invokeContext, string $piePackagePath, RootPackageInterface $pieRootPackage, PieJsonEditor $pieJsonEditor, InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('Installing PIE extension from <info>%s</info>', $piePackagePath));
        $pieJsonEditor
            ->ensureExists()
            ->addRepository('path', $piePackagePath);

        try {
            return ($this->invokeSubCommand)(
                $invokeContext,
                [
                    'command' => 'install',
                    'requested-package-and-version' => $pieRootPackage->getName() . ':*@dev',
                ],
                $input,
                $output,
            );
        } finally {
            $output->writeln(
                sprintf(
                    'Removing temporary path repository: %s',
                    $piePackagePath,
                ),
                OutputInterface::VERBOSITY_VERBOSE,
            );
            $pieJsonEditor->removeRepository($piePackagePath);
        }
    }
}
