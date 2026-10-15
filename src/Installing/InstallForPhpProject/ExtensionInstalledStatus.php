<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

enum ExtensionInstalledStatus
{
    case Installed;
    case Missing;
    case ConstraintNotMet;
}
