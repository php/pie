<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Composer\Util\HttpDownloader;
use Php\Pie\Settings;
use Psr\Clock\ClockInterface;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class FetchReferencePackageMap
{
    private const REFERENCE_LIST_URL = 'https://raw.githubusercontent.com/php/pie/HEAD/resources/package-map.json';

    public function __construct(
        private readonly HttpDownloader $httpDownloader,
        private readonly Settings $pieSettings,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(): ExtensionToPackageSelections
    {
    }
}
