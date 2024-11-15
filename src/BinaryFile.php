<?php

declare(strict_types=1);

namespace Php\Pie;

use function hash_file;

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
}
