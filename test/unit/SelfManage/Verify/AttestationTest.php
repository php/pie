<?php

declare(strict_types=1);

namespace Php\PieUnitTest\SelfManage\Verify;

use InvalidArgumentException;
use Php\Pie\SelfManage\Verify\Attestation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function base64_encode;

#[CoversClass(Attestation::class)]
final class AttestationTest extends TestCase
{
    public function testFromAttestationWithValidBundle(): void
    {
        $attestation = Attestation::fromAttestationBundleWithDsseEnvelope([
            'bundle' => [
                'verificationMaterial' => [
                    'certificate' => ['rawBytes' => 'some great certificate content. some great certificate content. some great certificate content.'],
                ],
                'dsseEnvelope' => [
                    'payload' => base64_encode('this is the amazing payload'),
                    'payloadType' => 'this is the payload type',
                    'signatures' => [['sig' => base64_encode('signature number one!')]],
                ],
            ],
        ]);

        self::assertSame(
            "-----BEGIN CERTIFICATE-----\n"
            . "some great certificate content. some great certificate content.\n"
            . "some great certificate content.\n"
            . "-----END CERTIFICATE-----\n",
            $attestation->certificate,
        );
        self::assertSame('this is the amazing payload', $attestation->dsseEnvelopePayload);
        self::assertSame('this is the payload type', $attestation->dsseEnvelopePayloadType);
        self::assertSame('signature number one!', $attestation->dsseEnvelopeSignature);
    }

    /**
     * @return list<array<array-key, array<array-key, mixed>>>
     *
     * @psalm-suppress PossiblyUnusedMethod https://github.com/psalm/psalm-plugin-phpunit/issues/131
     */
    public static function invalidBundleProvider(): array
    {
        return [
            [
                [],
            ],
            [
                [
                    'bundle' => [
                        'verificationMaterial' => [
                            'certificate' => ['rawBytes' => ''],
                        ],
                        'dsseEnvelope' => [
                            'payload' => base64_encode('this is the amazing payload'),
                            'payloadType' => 'this is the payload type',
                            'signatures' => [['sig' => base64_encode('signature number one!')]],
                        ],
                    ],
                ],
            ],
            [
                [
                    'bundle' => [
                        'verificationMaterial' => [
                            'certificate' => [],
                        ],
                        'dsseEnvelope' => [
                            'payload' => base64_encode('this is the amazing payload'),
                            'payloadType' => 'this is the payload type',
                            'signatures' => [['sig' => base64_encode('signature number one!')]],
                        ],
                    ],
                ],
            ],
            [
                [
                    'bundle' => [
                        'verificationMaterial' => [
                            'certificate' => ['rawBytes' => 'some great certificate content. some great certificate content. some great certificate content.'],
                        ],
                        'dsseEnvelope' => [
                            'payload' => '',
                            'payloadType' => 'this is the payload type',
                            'signatures' => [['sig' => base64_encode('signature number one!')]],
                        ],
                    ],
                ],
            ],
            [
                [
                    'bundle' => [
                        'verificationMaterial' => [
                            'certificate' => ['rawBytes' => 'some great certificate content. some great certificate content. some great certificate content.'],
                        ],
                        'dsseEnvelope' => [
                            'payloadType' => 'this is the payload type',
                            'signatures' => [['sig' => base64_encode('signature number one!')]],
                        ],
                    ],
                ],
            ],
            [
                [
                    'bundle' => [
                        'verificationMaterial' => [
                            'certificate' => ['rawBytes' => 'some great certificate content. some great certificate content. some great certificate content.'],
                        ],
                        'dsseEnvelope' => [
                            'payload' => base64_encode('this is the amazing payload'),
                            'payloadType' => '',
                            'signatures' => [['sig' => base64_encode('signature number one!')]],
                        ],
                    ],
                ],
            ],
            [
                [
                    'bundle' => [
                        'verificationMaterial' => [
                            'certificate' => ['rawBytes' => 'some great certificate content. some great certificate content. some great certificate content.'],
                        ],
                        'dsseEnvelope' => [
                            'payload' => base64_encode('this is the amazing payload'),
                            'signatures' => [['sig' => base64_encode('signature number one!')]],
                        ],
                    ],
                ],
            ],
            [
                [
                    'bundle' => [
                        'verificationMaterial' => [
                            'certificate' => ['rawBytes' => 'some great certificate content. some great certificate content. some great certificate content.'],
                        ],
                        'dsseEnvelope' => [
                            'payload' => base64_encode('this is the amazing payload'),
                            'payloadType' => 'this is the payload type',
                            'signatures' => [['sig' => '']],
                        ],
                    ],
                ],
            ],
            [
                [
                    'bundle' => [
                        'verificationMaterial' => [
                            'certificate' => ['rawBytes' => 'some great certificate content. some great certificate content. some great certificate content.'],
                        ],
                        'dsseEnvelope' => [
                            'payload' => base64_encode('this is the amazing payload'),
                            'payloadType' => 'this is the payload type',
                            'signatures' => [],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @param array<array-key, mixed> $invalidBundle */
    #[DataProvider('invalidBundleProvider')]
    public function testFromAttestationWithInvalidBundles(array $invalidBundle): void
    {
        self::expectException(InvalidArgumentException::class);
        Attestation::fromAttestationBundleWithDsseEnvelope($invalidBundle);
    }
}
