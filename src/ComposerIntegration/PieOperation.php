<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
enum PieOperation
{
    case Resolve;
    case Download;
    case Build;
    case Install;
    case Uninstall;

    public function shouldBuild(): bool
    {
        return $this === PieOperation::Build || $this === PieOperation::Install;
    }

    public function shouldInstall(): bool
    {
        return $this === PieOperation::Install;
    }
}
