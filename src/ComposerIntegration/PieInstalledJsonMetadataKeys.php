<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
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
}
