<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Php\Pie\Command\CommandHelper;
use Php\Pie\Util\Process;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_filter;
use function array_key_exists;
use function array_walk;
use function assert;
use function getcwd;
use function in_array;
use function is_string;

use const ARRAY_FILTER_USE_BOTH;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class InstallSelectedPackage
{
    public function withPieCli(string $selectedPackage, InputInterface $input, OutputInterface $output): void
    {
        /** @psalm-suppress TypeDoesNotContainType */
        $self = array_key_exists('PHP_SELF', $_SERVER) && is_string($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
        if ($self === '') {
            throw new RuntimeException('Could not find PHP_SELF');
        }

        $process = [
            $self,
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
                assert($output instanceof ConsoleOutputInterface);

                if ($outOrErr === \Symfony\Component\Process\Process::OUT) {
                    $output->write('   > ' . $message);

                    return;
                }

                $output->getErrorOutput()->write('   > ' . $message);
            },
        );
    }
}
