<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
enum WindowsCompiler
{
    case VC6;
    case VC8;
    case VC9;
    case VC11;
    case VC14;
    case VC15;
    case VS16;
}
