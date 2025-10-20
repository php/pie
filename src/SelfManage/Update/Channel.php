<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Update;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
enum Channel: string
{
    case Stable  = 'stable';
    case Preview = 'preview';
    case Nightly = 'nightly';
}
