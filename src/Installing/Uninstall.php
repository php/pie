<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Php\Pie\DependencyResolver\Package;
use Php\Pie\File\BinaryFile;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
interface Uninstall
{
    public function __invoke(Package $package): BinaryFile;
}
