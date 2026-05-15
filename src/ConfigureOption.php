<?php

declare(strict_types=1);

namespace Php\Pie;

use Webmozart\Assert\Assert;

use function array_key_exists;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
final class ConfigureOption
{
    /** @param non-empty-string $name */
    private function __construct(
        public readonly string $name,
        public readonly bool $needsValue,
        public readonly string $description,
    ) {
    }

    /** @param array<array-key, mixed> $configureOptionDefinition */
    public static function fromComposerJsonDefinition(array $configureOptionDefinition): self
    {
        Assert::keyExists($configureOptionDefinition, 'name');
        Assert::stringNotEmpty($configureOptionDefinition['name']);
        // Restrict to identifier characters that match real ./configure flag
        // conventions. Whitespace and shell metacharacters in this field flow
        // verbatim into argv, log output, and installed.json metadata.
        Assert::regex(
            $configureOptionDefinition['name'],
            '/^[a-zA-Z][a-zA-Z0-9_-]*$/',
            'php-ext.configure-options[].name must be a configure-flag identifier (got %s)',
        );

        $needsValue = false;
        if (array_key_exists('needs-value', $configureOptionDefinition)) {
            Assert::boolean($configureOptionDefinition['needs-value']);
            $needsValue = $configureOptionDefinition['needs-value'];
        }

        $description = '';
        if (array_key_exists('description', $configureOptionDefinition)) {
            Assert::string($configureOptionDefinition['description']);
            $description = $configureOptionDefinition['description'];
        }

        return new self(
            $configureOptionDefinition['name'],
            $needsValue,
            $description,
        );
    }
}
