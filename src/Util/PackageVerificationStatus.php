<?php

declare(strict_types=1);

namespace Php\Pie\Util;

enum PackageVerificationStatus
{
    case Verified;
    case ChecksumMismatch;
    case ActualBinaryNotFound;
    case InstalledBinaryMetadataMissing;
    case ChecksumMetadataMissing;
    case InstalledBinaryPathDoesNotMatchActualBinaryPath;
    case InstalledButDoesNotExistInRequires;

    public function description(): string
    {
        return match ($this) {
            self::Verified => Emoji::GREEN_CHECKMARK,
            self::ChecksumMismatch => Emoji::PROHIBITED . ' - checksum mismatch',
            self::ActualBinaryNotFound => Emoji::WARNING . ' - extension file not found',
            self::InstalledBinaryMetadataMissing => Emoji::WARNING . ' - installed extension metadata missing',
            self::ChecksumMetadataMissing => Emoji::WARNING . ' - binary checksum metadata missing',
            self::InstalledBinaryPathDoesNotMatchActualBinaryPath => Emoji::WARNING . ' - binary path mismatch',
            self::InstalledButDoesNotExistInRequires => Emoji::WARNING . '- installed but does not exist in pie.json',
        };
    }

    public function isVerified(): bool
    {
        return $this === self::Verified;
    }
}
