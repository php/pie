<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Php\Pie\ExtensionName;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function array_keys;
use function array_values;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class ExtensionToPackageSelections
{
    /** @param array<non-empty-string, non-empty-string> $map */
    private function __construct(private readonly array $map)
    {
    }

    /** @param array<string, string> $map */
    public static function fromArrayMap(array $map): self
    {
        Assert::allStringNotEmpty(array_keys($map));
        Assert::allStringNotEmpty(array_values($map));

        // @phpstan-ignore argument.type
        return new self($map);
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
