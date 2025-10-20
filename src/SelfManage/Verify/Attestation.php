<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Verify;

use Webmozart\Assert\Assert;

use function base64_decode;
use function wordwrap;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class Attestation
{
    /**
     * @param non-empty-string $certificate
     * @param non-empty-string $dsseEnvelopePayload
     * @param non-empty-string $dsseEnvelopePayloadType
     * @param non-empty-string $dsseEnvelopeSignature
     */
    private function __construct(
        public readonly string $certificate,
        public readonly string $dsseEnvelopePayload,
        public readonly string $dsseEnvelopePayloadType,
        public readonly string $dsseEnvelopeSignature,
    ) {
    }

    /** @param array<array-key, mixed> $attestation */
    public static function fromAttestationBundleWithDsseEnvelope(array $attestation): self
    {
        Assert::keyExists($attestation, 'bundle');
        Assert::isArray($attestation['bundle']);

        Assert::keyExists($attestation['bundle'], 'verificationMaterial');
        Assert::isArray($attestation['bundle']['verificationMaterial']);
        Assert::keyExists($attestation['bundle']['verificationMaterial'], 'certificate');
        Assert::isArray($attestation['bundle']['verificationMaterial']['certificate']);
        Assert::keyExists($attestation['bundle']['verificationMaterial']['certificate'], 'rawBytes');
        Assert::stringNotEmpty($attestation['bundle']['verificationMaterial']['certificate']['rawBytes']);

        Assert::keyExists($attestation['bundle'], 'dsseEnvelope');
        Assert::isArray($attestation['bundle']['dsseEnvelope']);
        Assert::keyExists($attestation['bundle']['dsseEnvelope'], 'payload');
        Assert::stringNotEmpty($attestation['bundle']['dsseEnvelope']['payload']);
        Assert::keyExists($attestation['bundle']['dsseEnvelope'], 'payloadType');
        Assert::stringNotEmpty($attestation['bundle']['dsseEnvelope']['payloadType']);
        Assert::keyExists($attestation['bundle']['dsseEnvelope'], 'signatures');
        Assert::isNonEmptyList($attestation['bundle']['dsseEnvelope']['signatures']);
        Assert::count($attestation['bundle']['dsseEnvelope']['signatures'], 1);
        Assert::keyExists($attestation['bundle']['dsseEnvelope']['signatures'], 0);
        Assert::isArray($attestation['bundle']['dsseEnvelope']['signatures'][0]);
        Assert::keyExists($attestation['bundle']['dsseEnvelope']['signatures'][0], 'sig');
        Assert::stringNotEmpty($attestation['bundle']['dsseEnvelope']['signatures'][0]['sig']);

        $decoratedCertificate = "-----BEGIN CERTIFICATE-----\n"
            . wordwrap($attestation['bundle']['verificationMaterial']['certificate']['rawBytes'], 67, "\n", true) . "\n"
            . "-----END CERTIFICATE-----\n";

        $decodedPayload = base64_decode($attestation['bundle']['dsseEnvelope']['payload']);
        Assert::stringNotEmpty($decodedPayload);

        $decodedSignature = base64_decode($attestation['bundle']['dsseEnvelope']['signatures'][0]['sig']);
        Assert::stringNotEmpty($decodedSignature);

        return new self(
            $decoratedCertificate,
            $decodedPayload,
            $attestation['bundle']['dsseEnvelope']['payloadType'],
            $decodedSignature,
        );
    }
}
