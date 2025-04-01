<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Verify;

use Php\Pie\File\BinaryFile;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;

use function implode;
use function is_array;
use function sprintf;
use function trim;

class FailedToVerifyRelease extends RuntimeException
{
    public static function fromInvalidSubjectDefinition(): self
    {
        return new self('Unable to extract subject digest from the dsseEnvelope in the attestation.');
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

    /** @param array<string,string>|string $issuer */
    public static function fromIssuerCertificateVerificationFailed(array|string $issuer): self
    {
        return new self(sprintf(
            'Failed to verify the attestation certificate was issued by trusted root %s',
            is_array($issuer) ? implode(',', $issuer) : $issuer,
        ));
    }

    /** @param array<string,string>|string $issuer */
    public static function fromNoIssuerCertificateInTrustedRoot(array|string $issuer): self
    {
        return new self(sprintf(
            'Could not find a trusted root certificate for issuer %s',
            is_array($issuer) ? implode(',', $issuer) : $issuer,
        ));
    }

    public static function fromMismatchingExtensionValues(string $extension, string $expected, string $actual): self
    {
        return new self(sprintf(
            'Attestation certificate extension %s mismatch; expected "%s", was "%s"',
            $extension,
            $expected,
            $actual,
        ));
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
