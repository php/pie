<?php

declare(strict_types=1);

namespace Php\Pie;

use Composer\Pcre\Preg;
use Composer\Semver\VersionParser;
use UnexpectedValueException;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class ExtensionVersion
{
    private function __construct(
        public readonly string $original,
        public readonly string $originalOrModified,
        public readonly string $normalised,
    ) {
    }

    public static function parse(string $original): self
    {
        $versionParser      = new VersionParser();
        $originalOrModified = $original;

        try {
            $normalised = $versionParser->normalize($original);
        } catch (UnexpectedValueException) {
            if (Preg::isMatchStrictGroups('{^(\d+\.\d+\.\d+(?:\.\d+)?)}', $original, $match)) {
                $originalOrModified = $match[1];
            } else {
                $originalOrModified = '0';
            }

            $normalised = $versionParser->normalize($originalOrModified);
        }

        return new self($original, $originalOrModified, $normalised);
    }

    public function wasModified(): bool
    {
        return $this->original !== $this->originalOrModified;
    }
}
