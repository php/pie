<?php

declare(strict_types=1);

namespace Php\Pie\TargetPhp;

use Composer\Repository\PlatformRepository;

class ResolveTargetPhpToPlatformRepository
{
    public function __invoke(PhpBinaryPath $phpBinaryPath): PlatformRepository
    {
        // @todo I expect we also need to map the extensions for the given PHP binary, somehow?
        return new PlatformRepository([], ['php' => $phpBinaryPath->version()]);
    }
}
