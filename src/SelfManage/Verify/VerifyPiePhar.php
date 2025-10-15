<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Verify;

use Composer\IO\IOInterface;
use Php\Pie\File\BinaryFile;
use Php\Pie\SelfManage\Update\ReleaseMetadata;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
interface VerifyPiePhar
{
    /** @throws FailedToVerifyRelease */
    public function verify(ReleaseMetadata $releaseMetadata, BinaryFile $pharFilename, IOInterface $io): void;
}
