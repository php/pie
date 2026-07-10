<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Verify;

use Composer\Downloader\TransportException;
use Composer\IO\IOInterface;
use Php\Pie\File\BinaryFile;
use Php\Pie\SelfManage\Update\FetchPieRelease;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Php\Pie\Util\Emoji;
use ThePhpFoundation\Attestation\FilenameWithChecksum;
use ThePhpFoundation\Attestation\FulcioSigstoreOidExtensions;
use ThePhpFoundation\Attestation\Verification\Exception\FailedToVerifyArtifact;
use ThePhpFoundation\Attestation\Verification\VerifyAttestation;

use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class FallbackVerificationUsingOpenSsl implements VerifyPiePhar
{
    /** @link https://github.com/sigstore/fulcio/blob/main/docs/oid-info.md#136141572641--fulcio */
    private const ATTESTATION_CERTIFICATE_EXPECTED_EXTENSION_VALUES = [
        FulcioSigstoreOidExtensions::ISSUER_V2 => 'https://token.actions.githubusercontent.com',
        FulcioSigstoreOidExtensions::SOURCE_REPOSITORY_URI => 'https://github.com/php/pie',
        FulcioSigstoreOidExtensions::SOURCE_REPOSITORY_OWNER_URI => 'https://github.com/php',
    ];

    private const ORGANISATION = 'php';

    private const ARTIFACT_FILENAME = 'pie.phar';

    /** @link https://github.com/sigstore/fulcio/blob/main/docs/oid-info.md#13614157264114--source-repository-ref */
    private const SOURCE_REPOSITORY_REF = '1.3.6.1.4.1.57264.1.14';

    /** @link https://github.com/sigstore/fulcio/blob/main/docs/oid-info.md#1361415726419--build-signer-uri */
    private const BUILD_SIGNER_URI = '1.3.6.1.4.1.57264.1.9';

    public function __construct(
        private readonly VerifyAttestation $verifyAttestation,
        private readonly FetchPieRelease $fetchPieRelease,
    ) {
    }

    public function verify(ReleaseMetadata $releaseMetadata, BinaryFile $pharFilename, IOInterface $io): void
    {
        // The fallback verifier checks cert chain, cert extension claims, DSSE
        // subject digest, and DSSE signature, but does NOT validate Rekor
        // transparency-log inclusion. `gh attestation verify` does. Surface the
        // reduced guarantees so users on shared / air-gapped hosts know.
        $io->writeError(
            '<warning>Falling back to OpenSSL verification (no Rekor inclusion check). Install `gh` for full attestation verification.</warning>',
        );

        $expectedExtensions = self::ATTESTATION_CERTIFICATE_EXPECTED_EXTENSION_VALUES;

        if ($releaseMetadata->tag === 'nightly') {
            $expectedExtensions[self::BUILD_SIGNER_URI] = sprintf(
                'https://github.com/php/pie/.github/workflows/build-assets.yml@refs/heads/%s',
                $this->fetchPieRelease->trunkBranch(),
            );
        } else {
            $expectedExtensions[self::SOURCE_REPOSITORY_REF] = 'refs/tags/' . $releaseMetadata->tag;
        }

        try {
            /** @psalm-suppress InvalidArgument */
            $this->verifyAttestation->verify(
                FilenameWithChecksum::fromFilenameAndChecksum($pharFilename->filePath, $pharFilename->checksum),
                self::ORGANISATION,
                self::ARTIFACT_FILENAME,
                $expectedExtensions,
            );
        } catch (FailedToVerifyArtifact $failedToVerifyArtifact) {
            throw FailedToVerifyRelease::fromAttestationException($failedToVerifyArtifact);
        } catch (TransportException $transportException) {
            if ($transportException->getStatusCode() === 401) {
                throw FailedToVerifyRelease::fromGithubAuthenticationFailure($transportException);
            }

            throw $transportException;
        }

        $io->write(sprintf(
            '<info>%s Verified the new PIE version (using fallback verification)</info>',
            Emoji::GREEN_CHECKMARK,
        ));
    }
}
