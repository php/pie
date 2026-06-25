<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\BuildTools;

use Composer\IO\IOInterface;
use Php\Pie\Platform\PackageManager;
use Php\Pie\Platform\TargetPlatform;
use Throwable;

use function array_unique;
use function array_values;
use function count;
use function implode;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class CheckAllBuildTools
{
    public static function buildToolsFactory(): self
    {
        return new self([
            new BinaryBuildToolFinder(
                'gcc',
                [
                    PackageManager::Apt->value => 'gcc',
                    PackageManager::Apk->value => 'build-base',
                    PackageManager::Dnf->value => 'gcc',
                    PackageManager::Yum->value => 'gcc',
                    PackageManager::Brew->value => 'gcc',
                ],
            ),
            new BinaryBuildToolFinder(
                'make',
                [
                    PackageManager::Apt->value => 'make',
                    PackageManager::Apk->value => 'build-base',
                    PackageManager::Dnf->value => 'make',
                    PackageManager::Yum->value => 'make',
                    PackageManager::Brew->value => 'make',
                ],
            ),
            new BinaryBuildToolFinder(
                'autoconf',
                [
                    PackageManager::Apt->value => 'autoconf',
                    PackageManager::Apk->value => 'autoconf',
                    PackageManager::Dnf->value => 'autoconf',
                    PackageManager::Yum->value => 'autoconf',
                    PackageManager::Brew->value => 'autoconf',
                ],
            ),
            new BinaryBuildToolFinder(
                'pkg-config',
                [
                    PackageManager::Apt->value => 'pkg-config',
                    PackageManager::Apk->value => 'pkgconfig',
                    PackageManager::Dnf->value => 'pkgconf-pkg-config',
                    PackageManager::Yum->value => 'pkgconf-pkg-config',
                    PackageManager::Brew->value => 'pkgconf',
                ],
            ),
            new BinaryBuildToolFinder(
                ['libtoolize', 'glibtoolize'],
                [
                    PackageManager::Apt->value => 'libtool',
                    PackageManager::Apk->value => 'libtool',
                    PackageManager::Dnf->value => 'libtool',
                    PackageManager::Yum->value => 'libtool',
                    PackageManager::Brew->value => 'libtool',
                ],
            ),
            new PhpizeBuildToolFinder(
                [
                    PackageManager::Apt->value => 'php-dev',
                    PackageManager::Apk->value => 'php{major}{minor}-dev',
                    PackageManager::Dnf->value => 'php-devel',
                    PackageManager::Yum->value => 'php-devel',
                    PackageManager::Brew->value => 'php',
                ],
            ),
        ]);
    }

    /** @param list<BinaryBuildToolFinder> $buildTools */
    public function __construct(
        private readonly array $buildTools,
    ) {
    }

    public function check(IOInterface $io, PackageManager|null $packageManager, TargetPlatform $targetPlatform, bool $autoInstallIfMissing): void
    {
        $io->write('<info>Checking if all build tools are installed.</info>', verbosity: IOInterface::VERBOSE);
        /** @var list<string> $packagesToInstall */
        $packagesToInstall = [];
        $missingTools      = [];
        $allFound          = true;

        foreach ($this->buildTools as $buildTool) {
            if ($buildTool->check($targetPlatform) !== false) {
                $io->write('Build tool ' . $buildTool->toolNames() . ' is installed.', verbosity: IOInterface::VERY_VERBOSE);
                continue;
            }

            $allFound       = false;
            $missingTools[] = $buildTool->toolNames();

            if ($packageManager === null) {
                continue;
            }

            $packageName = $buildTool->packageNameFor($packageManager, $targetPlatform);

            if ($packageName === null) {
                $io->writeError('<warning>Could not find package name for build tool ' . $buildTool->toolNames() . '.</warning>', verbosity: IOInterface::VERBOSE);
                continue;
            }

            $packagesToInstall[] = $packageName;
        }

        if ($allFound) {
            $io->write('<info>All build tools found.</info>', verbosity: IOInterface::VERBOSE);

            return;
        }

        $io->write('<comment>The following build tools are missing: ' . implode(', ', $missingTools) . '</comment>');

        if ($packageManager === null) {
            $io->write('<warning>Could not find a package manager to install the missing build tools.</warning>');

            return;
        }

        if (! count($packagesToInstall)) {
            $io->write('<warning>Could not determine packages to install.</warning>');

            return;
        }

        $proposedInstallCommand = implode(' ', $packageManager->installCommand(array_values(array_unique($packagesToInstall))));

        if (! $io->isInteractive() && ! $autoInstallIfMissing) {
            $io->writeError('<warning>You are not running in interactive mode, and you did not provide the --auto-install-build-tools flag.');
            $io->writeError('You may need to run: ' . $proposedInstallCommand . '</warning>');
            $io->writeError('');

            return;
        }

        $io->write('The following command will be run: ' . $proposedInstallCommand, verbosity: IOInterface::VERBOSE);

        if ($io->isInteractive() && ! $autoInstallIfMissing) {
            if (! $io->askConfirmation('<question>Would you like to install them now?</question>', false)) {
                $io->write('<comment>Ok, but things might not work. Just so you know.</comment>');

                return;
            }
        }

        try {
            $packageManager->install($io, array_values(array_unique($packagesToInstall)));

            $io->write('<info>Missing build tools have been installed.</info>');
        } catch (Throwable $throwable) {
            $io->writeError('<error>Could not install the missing build tools. You may need to install them manually.</error>');
            $io->writeError($throwable->__toString(), verbosity: IOInterface::VERBOSE);
            $io->writeError('');

            return;
        }
    }
}
