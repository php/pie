<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Update;

use Php\Pie\File\BinaryFile;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
interface FetchPieRelease
{
    public function latestReleaseMetadata(Channel $updateChannel): ReleaseMetadata;

    /** Download the given pie.phar and return the filename (should be a temp file) */
    public function downloadContent(ReleaseMetadata $releaseMetadata): BinaryFile;
}
