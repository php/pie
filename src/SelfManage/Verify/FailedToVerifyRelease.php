<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Verify;

use Php\Pie\File\BinaryFile;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use RuntimeException;

use function sprintf;

class FailedToVerifyRelease extends RuntimeException
{
    public static function fromInvalidSubjectDefinition(): self
    {
        return new self('Invalid subject definition in attestation payload');
    }

    public static function fromMissingAttestation(ReleaseMetadata $releaseMetadata, BinaryFile $file): self
    {
        return new self(sprintf(
            'Attestation for %s (sha256:%s) was not found',
            $releaseMetadata->tag,
            $file->checksum,
        ));
    }

    public static function fromSignatureVerificationFailed(int $attestationIndex, ReleaseMetadata $releaseMetadata): self
    {
        return new self(sprintf(
            'Failed to verify DSSE Envelope payload signature for attestation %d for %s',
            $attestationIndex,
            $releaseMetadata->tag,
        ));
    }

    public static function fromNoOpenssl(): self
    {
        return new self('Unable to verify without `gh` CLI tool, or openssl extension.');
    }
}
