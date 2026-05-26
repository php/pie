<?php

declare(strict_types=1);

namespace Php\Pie\File;

use Php\Pie\Util;

use function file_exists;
use function hash;
use function hash_equals;
use function Safe\hash_file;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
final class BinaryFile
{
    private const HASH_TYPE_SHA256 = 'sha256';

    /**
     * @param non-empty-string $filePath
     * @param non-empty-string $checksum
     */
    public function __construct(
        public readonly string $filePath,
        public readonly string $checksum,
    ) {
    }

    /** @param non-empty-string $filePath */
    public static function fromFileWithSha256Checksum(string $filePath): self
    {
        return new self(
            $filePath,
            hash_file(self::HASH_TYPE_SHA256, $filePath),
        );
    }

    public function verify(): void
    {
        if (! file_exists($this->filePath)) {
            throw Util\FileNotFound::fromFilename($this->filePath);
        }

        $this->verifyAgainstOther(self::fromFileWithSha256Checksum($this->filePath));
    }

    /** @throws BinaryFileFailedVerification */
    public function verifyContent(string $content): void
    {
        $contentChecksum = hash(self::HASH_TYPE_SHA256, $content);

        if (! hash_equals($this->checksum, $contentChecksum)) {
            throw BinaryFileFailedVerification::fromChecksumMismatch($this, new self($this->filePath, $contentChecksum));
        }
    }

    /** @throws BinaryFileFailedVerification */
    public function verifyAgainstOther(self $other): void
    {
        if ($this->filePath !== $other->filePath) {
            throw BinaryFileFailedVerification::fromFilenameMismatch($this, $other);
        }

        if (! hash_equals($this->checksum, $other->checksum)) {
            throw BinaryFileFailedVerification::fromChecksumMismatch($this, $other);
        }
    }
}
