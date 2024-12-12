<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Php\Pie\BinaryFile;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPlatform;
use Symfony\Component\Console\Output\OutputInterface;

use function array_key_exists;
use function preg_match;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class StandardSinglePhpIni implements SetupIniApproach
{
    public function __construct(
        private readonly CheckAndAddExtensionToIniIfNeeded $checkAndAddExtensionToIniIfNeeded,
    ) {
    }

    public function canBeUsed(TargetPlatform $targetPlatform): bool
    {
        return $this->extractPhpIniFromPhpInfo($targetPlatform->phpBinaryPath->phpinfo()) !== null;
    }

    public function setup(
        TargetPlatform $targetPlatform,
        DownloadedPackage $downloadedPackage,
        BinaryFile $binaryFile,
        OutputInterface $output,
    ): bool {
        $ini = $this->extractPhpIniFromPhpInfo($targetPlatform->phpBinaryPath->phpinfo());

        /** In practice, this shouldn't happen since {@see canBeUsed()} checks this */
        if ($ini === null) {
            return false;
        }

        return ($this->checkAndAddExtensionToIniIfNeeded)(
            $ini,
            $targetPlatform,
            $downloadedPackage,
            $output,
        );
    }

    /** @return non-empty-string|null */
    private function extractPhpIniFromPhpInfo(string $phpinfoString): string|null
    {
        if (
            preg_match('/Loaded Configuration File([ =>\t]*)(.*)/', $phpinfoString, $m)
            && array_key_exists(2, $m)
            && $m[2] !== ''
            && $m[2] !== '(none)'
        ) {
            return $m[2];
        }

        return null;
    }
}
