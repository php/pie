<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Downloading\Exception\CouldNotFindReleaseAsset;
use RuntimeException;

use function file_exists;
use function implode;
use function sprintf;
use function strtolower;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class WindowsExtensionAssetName
{
    /** @psalm-suppress UnusedConstructor */
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
         */
        return [
            strtolower(sprintf(
                'php_%s-%s-%s-%s-%s-%s.%s',
                $package->extensionName()->name(),
                $package->version(),
                $targetPlatform->phpBinaryPath->majorMinorVersion(),
                $targetPlatform->threadSafety->asShort(),
                strtolower($targetPlatform->windowsCompiler->name),
                $targetPlatform->architecture->name,
                $fileExtension,
            )),
            strtolower(sprintf(
                'php_%s-%s-%s-%s-%s-%s.%s',
                $package->extensionName()->name(),
                $package->version(),
                $targetPlatform->phpBinaryPath->majorMinorVersion(),
                strtolower($targetPlatform->windowsCompiler->name),
                $targetPlatform->threadSafety->asShort(),
                $targetPlatform->architecture->name,
                $fileExtension,
            )),
        ];
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

        throw new RuntimeException('Unable to find DLL for package, checked: ' . implode(', ', $possibleDllNames));
    }
}
