<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallNotification;

use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPlatform;

/**
 * Send a notification to Packagist that the package was installed. This is
 * used for package analytics (i.e. download counts)
 *
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 */
interface InstallNotification
{
    /** @throws FailedToSendInstallNotification */
    public function send(
        TargetPlatform $targetPlatform,
        DownloadedPackage $package,
    ): void;
}
