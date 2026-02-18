<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

use Php\Pie\DependencyResolver\Package;

use function array_unique;
use function array_values;
use function sprintf;
use function strtolower;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class PrePackagedBinaryAssetName
{
    private function __construct()
    {
    }

    /** @return non-empty-list<non-empty-string> */
    public static function packageNames(TargetPlatform $targetPlatform, Package $package): array
    {
        $names = [];
        foreach ($targetPlatform->architecture->allNames() as $arch) {
            $names[] = strtolower(sprintf(
                'php_%s-%s_php%s-%s-%s-%s%s%s.zip',
                $package->extensionName()->name(),
                $package->version(),
                $targetPlatform->phpBinaryPath->majorMinorVersion(),
                $arch,
                $targetPlatform->operatingSystemFamily->value,
                $targetPlatform->libcFlavour()->value,
                $targetPlatform->phpBinaryPath->debugMode() === DebugBuild::Debug ? '-debug' : '',
                $targetPlatform->threadSafety === ThreadSafetyMode::ThreadSafe ? '-zts' : '',
            ));
            $names[] = strtolower(sprintf(
                'php_%s-%s_php%s-%s-%s-%s%s%s.tgz',
                $package->extensionName()->name(),
                $package->version(),
                $targetPlatform->phpBinaryPath->majorMinorVersion(),
                $arch,
                $targetPlatform->operatingSystemFamily->value,
                $targetPlatform->libcFlavour()->value,
                $targetPlatform->phpBinaryPath->debugMode() === DebugBuild::Debug ? '-debug' : '',
                $targetPlatform->threadSafety === ThreadSafetyMode::ThreadSafe ? '-zts' : '',
            ));
            $names[] = strtolower(sprintf(
                'php_%s-%s_php%s-%s-%s-%s%s%s.zip',
                $package->extensionName()->name(),
                $package->version(),
                $targetPlatform->phpBinaryPath->majorMinorVersion(),
                $arch,
                $targetPlatform->operatingSystemFamily->value,
                $targetPlatform->libcFlavour()->value,
                $targetPlatform->phpBinaryPath->debugMode() === DebugBuild::Debug ? '-debug' : '',
                $targetPlatform->threadSafety === ThreadSafetyMode::ThreadSafe ? '-zts' : '-nts',
            ));
            $names[] = strtolower(sprintf(
                'php_%s-%s_php%s-%s-%s-%s%s%s.tgz',
                $package->extensionName()->name(),
                $package->version(),
                $targetPlatform->phpBinaryPath->majorMinorVersion(),
                $arch,
                $targetPlatform->operatingSystemFamily->value,
                $targetPlatform->libcFlavour()->value,
                $targetPlatform->phpBinaryPath->debugMode() === DebugBuild::Debug ? '-debug' : '',
                $targetPlatform->threadSafety === ThreadSafetyMode::ThreadSafe ? '-zts' : '-nts',
            ));
        }

        return array_values(array_unique($names));
    }
}
