<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Php\Pie\BinaryFile;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPhp\Exception\ExtensionIsNotLoaded;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Util\Process;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;

use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class DockerPhpExtEnable implements SetupIniApproach
{
    private const DOCKER_PHP_EXT_ENABLE = 'docker-php-ext-enable';

    public function __construct(private readonly string $dockerPhpExtEnableName = self::DOCKER_PHP_EXT_ENABLE)
    {
    }

    public function canBeUsed(TargetPlatform $targetPlatform): bool
    {
        return $this->dockerPhpExtEnablePath() !== null;
    }

    public function setup(
        TargetPlatform $targetPlatform,
        DownloadedPackage $downloadedPackage,
        BinaryFile $binaryFile,
        OutputInterface $output,
    ): bool {
        $dockerPhpExtEnable = $this->dockerPhpExtEnablePath();

        if ($dockerPhpExtEnable === null) {
            return false;
        }

        try {
            $enableOutput = Process::run([$dockerPhpExtEnable, $downloadedPackage->package->extensionName->name()]);
        } catch (ProcessFailedException $processFailed) {
            $output->writeln(
                sprintf(
                    'Could not enable extension %s using %s. Exception was: %s',
                    $downloadedPackage->package->extensionName->name(),
                    $this->dockerPhpExtEnableName,
                    $processFailed->getMessage(),
                ),
                OutputInterface::VERBOSITY_VERBOSE,
            );

            return false;
        }

        try {
            $targetPlatform->phpBinaryPath->assertExtensionIsLoadedInRuntime(
                $downloadedPackage->package->extensionName,
                $output,
            );

            return true;
        } catch (ExtensionIsNotLoaded) {
            $output->writeln(
                sprintf(
                    'Asserting that extension %s was enabled using %s failed. Output was: %s',
                    $downloadedPackage->package->extensionName->name(),
                    $this->dockerPhpExtEnableName,
                    $enableOutput !== '' ? $enableOutput : '(empty)',
                ),
                OutputInterface::VERBOSITY_VERBOSE,
            );

            return false;
        }
    }

    private function dockerPhpExtEnablePath(): string|null
    {
        try {
            return Process::run(['which', $this->dockerPhpExtEnableName]);
        } catch (ProcessFailedException) {
            return null;
        }
    }
}
