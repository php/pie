<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Php\Pie\ExtensionName;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_values;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class ExtensionToPackageSelections
{
    // @todo real URL
    private const REFERENCE_LIST_URL = __DIR__ . '/../../../resources/package-map.json';

    /** @param array<non-empty-string, non-empty-string> $map */
    private function __construct(private readonly array $map)
    {
    }

    public static function fromReferenceList(): self
    {
        // @todo timeout should be VERY quick; we don't want connectivity holdups
        // @todo maybe an ENV or CLI param to skip fetching the reference list?
        // @todo verify attestation/signature of reference list
        // @todo cache the list locally
        $x = file_get_contents(self::REFERENCE_LIST_URL);
        $xx = json_decode($x, true, flags: JSON_THROW_ON_ERROR);
        return new self($xx);
    }

    /** @param array<string, string> $map */
    public static function fromArrayMap(array $map): self
    {
        Assert::allStringNotEmpty(array_keys($map));
        Assert::allStringNotEmpty(array_values($map));

        // @phpstan-ignore argument.type
        return new self($map);
    }

    public function overrideWith(ExtensionToPackageSelections $overrides): self
    {
        return new self(array_merge($this->map, $overrides->map));
    }

    public function has(ExtensionName $extension): bool
    {
        return array_key_exists($extension->name(), $this->map);
    }

    /** @return non-empty-string */
    public function packageFor(ExtensionName $extension): string
    {
        Assert::keyExists($this->map, $extension->name());

        return $this->map[$extension->name()];
    }
}
