<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

use function array_combine;
use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class InvokeSubCommand
{
    public function __construct(private readonly OutputInterface $output)
    {
    }

    /** @param array<array-key, mixed> $subCommandInput */
    public function __invoke(
        Command $command,
        array $subCommandInput,
        InputInterface $originalCommandInput,
    ): int {
        $originalSuppliedOptions = array_filter($originalCommandInput->getOptions());
        $installForProjectInput  = new ArrayInput(array_merge(
            $subCommandInput,
            array_combine(
                array_map(static fn ($key) => '--' . $key, array_keys($originalSuppliedOptions)),
                array_values($originalSuppliedOptions),
            ),
        ));

        $application = $command->getApplication();
        Assert::notNull($application);

        return $application->doRun($installForProjectInput, $this->output);
    }
}
