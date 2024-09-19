<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_walk;
use function sprintf;

#[AsCommand(
    name: 'show',
    description: 'List the installed modules and their versions.',
)]
final class ShowCommand extends Command
{
    public function configure(): void
    {
        parent::configure();

        CommandHelper::configurePhpConfigOptions($this);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $output);

        $extensions = $targetPlatform->phpBinaryPath->extensions();
        $output->writeln("\n" . '<info>Loaded extensions:</info>');
        array_walk(
            $extensions,
            static function (string $version, string $name) use ($output): void {
                $output->writeln(sprintf('%s:%s', $name, $version));
            },
        );

        return Command::SUCCESS;
    }
}
