<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Package\CompletePackageInterface;

use function array_column;
use function array_key_exists;
use function is_string;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @psalm-type PieMetadata = array{
 *     pie-target-platform-php-path?: non-empty-string,
 *     pie-target-platform-php-config-path?: non-empty-string,
 *     pie-target-platform-php-version?: non-empty-string,
 *     pie-target-platform-php-thread-safety?: non-empty-string,
 *     pie-target-platform-php-windows-compiler?: non-empty-string,
 *     pie-target-platform-architecture?: non-empty-string,
 *     pie-configure-options?: non-empty-string,
 *     pie-built-binary?: non-empty-string,
 *     pie-installed-binary-checksum?: non-empty-string,
 *     pie-installed-binary?: non-empty-string,
 *     pie-phpize-binary?: non-empty-string,
 * }
 */
enum PieInstalledJsonMetadataKeys: string
{
    case TargetPlatformPhpPath            = 'pie-target-platform-php-path';
    case TargetPlatformPhpConfigPath      = 'pie-target-platform-php-config-path';
    case TargetPlatformPhpVersion         = 'pie-target-platform-php-version';
    case TargetPlatformPhpThreadSafety    = 'pie-target-platform-php-thread-safety';
    case TargetPlatformPhpWindowsCompiler = 'pie-target-platform-php-windows-compiler';
    case TargetPlatformArchitecture       = 'pie-target-platform-architecture';
    case ConfigureOptions                 = 'pie-configure-options';
    case BuiltBinary                      = 'pie-built-binary';
    case BinaryChecksum                   = 'pie-installed-binary-checksum';
    case InstalledBinary                  = 'pie-installed-binary';
    case PhpizeBinary                     = 'pie-phpize-binary';

    /** @return PieMetadata */
    public static function pieMetadataFromComposerPackage(CompletePackageInterface $composerPackage): array
    {
        $composerPackageExtras = $composerPackage->getExtra();

        $onlyPieExtras = [];

        foreach (array_column(self::cases(), 'value') as $pieMetadataKey) {
            if (
                ! array_key_exists($pieMetadataKey, $composerPackageExtras)
                || ! is_string($composerPackageExtras[$pieMetadataKey])
                || $composerPackageExtras[$pieMetadataKey] === ''
            ) {
                continue;
            }

            $onlyPieExtras[$pieMetadataKey] = $composerPackageExtras[$pieMetadataKey];
        }

        return $onlyPieExtras;
    }
}
