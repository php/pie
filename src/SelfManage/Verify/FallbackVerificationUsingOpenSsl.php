<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Verify;

use Composer\Config;
use Composer\Downloader\TransportException;
use Composer\IO\IOInterface;
use Composer\Util\HttpDownloader;
use Php\Pie\ComposerIntegration\QuieterConsoleIO;
use Php\Pie\File\BinaryFile;
use Php\Pie\SelfManage\Update\FetchPieRelease;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Php\Pie\Util\Emoji;
use ThePhpFoundation\Attestation\BundleSource\BundleSource;
use ThePhpFoundation\Attestation\BundleSource\DownloadGitHubBundle;
use ThePhpFoundation\Attestation\FilenameWithChecksum;
use ThePhpFoundation\Attestation\FulcioSigstoreOidExtensions;
use ThePhpFoundation\Attestation\Verification\Exception\FailedToVerifyArtifact;
use ThePhpFoundation\Attestation\Verification\VerifyBundleWithOpenSsl;
use Throwable;

use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class FallbackVerificationUsingOpenSsl implements VerifyPiePhar
{
    /** @link https://github.com/sigstore/fulcio/blob/main/docs/oid-info.md#136141572641--fulcio */
    private const ATTESTATION_CERTIFICATE_EXPECTED_EXTENSION_VALUES = [
        FulcioSigstoreOidExtensions::SOURCE_REPOSITORY_URI => 'https://github.com/php/pie',
        FulcioSigstoreOidExtensions::SOURCE_REPOSITORY_OWNER_URI => 'https://github.com/php',
    ];

    private const OIDC_ISSUER = 'https://token.actions.githubusercontent.com';

    private const ORGANISATION = 'php';

    public function __construct(
        private readonly FetchPieRelease $fetchPieRelease,
        private readonly BundleSource $bundleSource,
    ) {
    }

    /** @param non-empty-string $githubApiBaseUrl */
    public static function factory(FetchPieRelease $fetchPieRelease, QuieterConsoleIO $io, Config $config, string $githubApiBaseUrl): self
    {
        return new self(
            $fetchPieRelease,
            new DownloadGitHubBundle(self::ORGANISATION, $githubApiBaseUrl, new HttpDownloader($io, $config)),
        );
    }

    public function verify(ReleaseMetadata $releaseMetadata, BinaryFile $pharFilename, IOInterface $io): void
    {
        $io->writeError(
            '<warning>Falling back to OpenSSL verification. Install `gh` to verify using the GitHub CLI instead.</warning>',
        );

        if ($releaseMetadata->tag === 'nightly') {
            $expectedCertificateIdentity = sprintf(
                'https://github.com/php/pie/.github/workflows/build-assets.yml@refs/heads/%s',
                $this->fetchPieRelease->trunkBranch(),
            );
        } else {
            $expectedCertificateIdentity = sprintf(
                'https://github.com/php/pie/.github/workflows/build-assets.yml@refs/tags/%s',
                $releaseMetadata->tag,
            );
        }

        try {
            $file    = FilenameWithChecksum::fromFilenameAndChecksum($pharFilename->filePath, $pharFilename->checksum);
            $bundles = $this->bundleSource->getBundles($file);

            VerifyBundleWithOpenSsl::factory(
                self::ATTESTATION_CERTIFICATE_EXPECTED_EXTENSION_VALUES,
                $expectedCertificateIdentity,
                self::OIDC_ISSUER,
            )->verify($bundles, $file);
        } catch (FailedToVerifyArtifact $failedToVerifyArtifact) {
            throw FailedToVerifyRelease::fromAttestationException($failedToVerifyArtifact);
        } catch (TransportException $transportException) {
            if ($transportException->getStatusCode() === 401) {
                throw FailedToVerifyRelease::fromGithubAuthenticationFailure($transportException);
            }

            throw $transportException;
        } catch (Throwable $throwable) {
            throw FailedToVerifyRelease::fromUnexpectedException($throwable);
        }

        $io->write(sprintf(
            '<info>%s Verified the new PIE version (using fallback verification)</info>',
            Emoji::GREEN_CHECKMARK,
        ));
    }
}
