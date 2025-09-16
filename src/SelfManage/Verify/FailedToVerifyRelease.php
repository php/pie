<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Verify;

use Php\Pie\SelfManage\Update\ReleaseMetadata;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use ThePhpFoundation\Attestation\Verification\Exception\FailedToVerifyArtifact;

use function sprintf;
use function trim;

class FailedToVerifyRelease extends RuntimeException
{
    public static function fromAttestationException(FailedToVerifyArtifact $failedToVerifyArtifact): self
    {
        return new self($failedToVerifyArtifact->getMessage(), 0, $failedToVerifyArtifact);
    }

    public static function fromNoOpenssl(): self
    {
        return new self('Unable to verify without `gh` CLI tool, or openssl extension.');
    }

    public static function fromGhCliFailure(ReleaseMetadata $releaseMetadata, ProcessFailedException $processFailedException): self
    {
        return new self(
            sprintf(
                "`gh` CLI tool could not verify release %s\n\nError: %s",
                $releaseMetadata->tag,
                trim($processFailedException->getProcess()->getErrorOutput()),
            ),
            previous: $processFailedException,
        );
    }
}
