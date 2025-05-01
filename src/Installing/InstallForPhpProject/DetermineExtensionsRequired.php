<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Composer\Composer;
use Composer\Package\Link;
use Composer\Repository\InstalledRepository;
use Composer\Repository\RootPackageRepository;
use Php\Pie\ExtensionName;

use function array_filter;
use function in_array;
use function ksort;
use function str_starts_with;
use function strlen;
use function substr;

class DetermineExtensionsRequired
{
    public static function linkFilter(Link $link): bool
    {
        $linkTarget = $link->getTarget();
        if (! str_starts_with($linkTarget, 'ext-')) {
            return false;
        }

        return ExtensionName::isValidExtensionName(substr($linkTarget, strlen('ext-')));
    }

    /** @return array<string, Link> */
    public function forProject(Composer $composer): array
    {
        $requires          = [];
        $removeDevPackages = [];

        /** {@see \Composer\Command\CheckPlatformReqsCommand::execute} */
        $installedRepo = $composer->getRepositoryManager()->getLocalRepository();
        if (! $installedRepo->getPackages()) {
            $installedRepo = $composer->getLocker()->getLockedRepository();
        } else {
            $removeDevPackages = $installedRepo->getDevPackageNames();
        }

        foreach (array_filter($composer->getPackage()->getDevRequires(), [self::class, 'linkFilter']) as $require => $link) {
            $requires[$require] = $link;
        }

        $installedRepo = new InstalledRepository([$installedRepo, new RootPackageRepository(clone $composer->getPackage())]);

        foreach ($installedRepo->getPackages() as $package) {
            if (in_array($package->getName(), $removeDevPackages, true)) {
                continue;
            }

            foreach (array_filter($package->getRequires(), [self::class, 'linkFilter']) as $require => $link) {
                $requires[$require] = $link;
            }
        }

        ksort($requires);

        return $requires;
    }
}
