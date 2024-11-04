<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

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
}
