<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Verify;

use Php\Pie\File\BinaryFile;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use RuntimeException;

use function sprintf;

class FailedToVerifyRelease extends RuntimeException
{
    public static function fromMissingAttestation(ReleaseMetadata $releaseMetadata, BinaryFile $file): self
    {
        return new self(sprintf(
            'Attestation for %s (sha256:%s) was not found',
            $releaseMetadata->tag,
            $file->checksum,
        ));
    }
}
