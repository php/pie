<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Update;

enum Channel: string
{
    case Stable = 'stable';
    case Preview = 'preview';
    case Nightly = 'nightly';
}
