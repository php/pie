<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Php\Pie\BinaryFile;
use Php\Pie\DependencyResolver\Package;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
interface Uninstall
{
    public function __invoke(Package $package): BinaryFile;
}
