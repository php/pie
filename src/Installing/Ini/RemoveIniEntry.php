<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Php\Pie\DependencyResolver\Package;
use Php\Pie\Platform\TargetPlatform;
use Symfony\Component\Console\Output\OutputInterface;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
interface RemoveIniEntry
{
    /** @return list<string> Returns a list of INI files that were updated to remove the extension */
    public function __invoke(Package $package, TargetPlatform $targetPlatform, OutputInterface $output): array;
}
