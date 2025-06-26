<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Php\Pie\Command\CommandHelper;
use Php\Pie\File\FullPathToSelf;
use Php\Pie\Util\Process;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_filter;
use function array_walk;
use function getcwd;
use function in_array;

use const ARRAY_FILTER_USE_BOTH;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class InstallSelectedPackage
{
    public function __construct(private readonly FullPathToSelf $fullPathToSelf)
    {
    }

    public function withPieCli(string $selectedPackage, InputInterface $input, OutputInterface $output): void
    {
        $process = [
            ($this->fullPathToSelf)(),
            'install',
            $selectedPackage,
        ];

        $phpPathOptions = array_filter(
            $input->getOptions(),
            static function (mixed $value, string|int $key): bool {
                return $value !== null
                    && $value !== false
                    && in_array(
                        $key,
                        [
                            CommandHelper::OPTION_WITH_PHP_CONFIG,
                            CommandHelper::OPTION_WITH_PHP_PATH,
                            CommandHelper::OPTION_WITH_PHPIZE_PATH,
                        ],
                    );
            },
            ARRAY_FILTER_USE_BOTH,
        );

        array_walk(
            $phpPathOptions,
            static function (string $value, string $key) use (&$process): void {
                $process[] = '--' . $key;
                $process[] = $value;
            },
        );

        Process::run(
            $process,
            getcwd(),
            null,
            static function (string $outOrErr, string $message) use ($output): void {
                if ($output instanceof ConsoleOutputInterface && $outOrErr === \Symfony\Component\Process\Process::ERR) {
                    $output->getErrorOutput()->write('   > ' . $message);

                    return;
                }

                $output->write('   > ' . $message);
            },
        );
    }
}
