<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Verify;

use Composer\Downloader\TransportException;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use ThePhpFoundation\Attestation\Verification\Exception\FailedToVerifyArtifact;

use function sprintf;
use function trim;

class FailedToVerifyRelease extends RuntimeException
{
    public static function fromGithubAuthenticationFailure(TransportException $transportException): self
    {
        return new self(
            $transportException->getMessage() . ' while downloading attestation to verify PIE. This likely '
            . 'means you have not set up GitHub authentication in Composer yet. PIE relies on this Composer '
            . 'authentication configuration to make API requests to GitHub; check out '
            . 'https://getcomposer.org/doc/articles/authentication-for-private-packages.md#command-line-github-oauth'
            . 'for help configuring Composer, or set GITHUB_TOKEN if the environment makes sense',
            previous: $transportException,
        );
    }

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
