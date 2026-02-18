<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Downloading\Exception\CouldNotFindReleaseAsset;
use RuntimeException;

use function array_unique;
use function array_values;
use function file_exists;
use function implode;
use function ltrim;
use function sprintf;
use function strtolower;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class WindowsExtensionAssetName
{
    private function __construct()
    {
    }

    /** @return non-empty-list<non-empty-string> */
    private static function assetNames(TargetPlatform $targetPlatform, Package $package, string $fileExtension): array
    {
        if ($targetPlatform->operatingSystem !== OperatingSystem::Windows || $targetPlatform->windowsCompiler === null) {
            throw CouldNotFindReleaseAsset::forMissingWindowsCompiler($targetPlatform);
        }

        /**
         * During development, we swapped compiler/ts around. It is fairly trivial to support both, so we can check
         * both formats pretty easily, just to avoid confusion for package maintainers...
         *
         * Additionally, some distributions (notably downloads.php.net) use alternative architecture labels
         * (e.g. "x64" instead of "x86_64"), and version strings without the "v" prefix (e.g. "5.1.28" instead
         * of "v5.1.28"). We generate variants covering all combinations to match either convention.
         */
        $version       = $package->version();
        $versionNoV    = ltrim($version, 'vV');
        $versions      = array_unique([$version, $versionNoV]);
        $architectures = $targetPlatform->architecture->allNames();

        $names = [];
        foreach ($versions as $ver) {
            foreach ($architectures as $arch) {
                // Format: {ts}-{compiler} (e.g. ts-vs17)
                $names[] = strtolower(sprintf(
                    'php_%s-%s-%s-%s-%s-%s.%s',
                    $package->extensionName()->name(),
                    $ver,
                    $targetPlatform->phpBinaryPath->majorMinorVersion(),
                    $targetPlatform->threadSafety->asShort(),
                    strtolower($targetPlatform->windowsCompiler->name),
                    $arch,
                    $fileExtension,
                ));
                // Format: {compiler}-{ts} (e.g. vs17-ts) — legacy/swapped ordering
                $names[] = strtolower(sprintf(
                    'php_%s-%s-%s-%s-%s-%s.%s',
                    $package->extensionName()->name(),
                    $ver,
                    $targetPlatform->phpBinaryPath->majorMinorVersion(),
                    strtolower($targetPlatform->windowsCompiler->name),
                    $targetPlatform->threadSafety->asShort(),
                    $arch,
                    $fileExtension,
                ));
            }
        }

        return array_values(array_unique($names));
    }

    /** @return non-empty-list<non-empty-string> */
    public static function zipNames(TargetPlatform $targetPlatform, Package $package): array
    {
        return self::assetNames($targetPlatform, $package, 'zip');
    }

    /** @return non-empty-list<non-empty-string> */
    public static function dllNames(TargetPlatform $targetPlatform, Package $package): array
    {
        return self::assetNames($targetPlatform, $package, 'dll');
    }

    /** @return non-empty-string */
    public static function determineDllName(TargetPlatform $targetPlatform, DownloadedPackage $package): string
    {
        $possibleDllNames = self::dllNames($targetPlatform, $package->package);
        foreach ($possibleDllNames as $dllName) {
            $fullDllName = $package->extractedSourcePath . '/' . $dllName;
            if (file_exists($fullDllName)) {
                return $fullDllName;
            }
        }

        // Zips from downloads.php.net use a simple naming convention (e.g. "php_apcu.dll")
        // without version/platform suffixes, so check for that as a fallback.
        $simpleDllName     = 'php_' . $package->package->extensionName()->name() . '.dll';
        $fullSimpleDllName = $package->extractedSourcePath . '/' . $simpleDllName;
        if (file_exists($fullSimpleDllName)) {
            return $fullSimpleDllName;
        }

        $possibleDllNames[] = $simpleDllName;

        throw new RuntimeException('Unable to find DLL for package, checked: ' . implode(', ', $possibleDllNames));
    }
}
