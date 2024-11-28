<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Package\CompletePackageInterface;
use InvalidArgumentException;
use Php\Pie\ConfigureOption;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\OperatingSystemFamily;

use Webmozart\Assert\Assert;
use function array_key_exists;
use function array_map;
use function array_slice;
use function explode;
use function implode;
use function parse_url;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function ucfirst;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
final class Package
{
    /**
     * @param list<ConfigureOption>                      $configureOptions
     * @param non-empty-list<OperatingSystemFamily>|null $compatibleOsFamilies
     * @param non-empty-list<OperatingSystemFamily>|null $incompatibleOsFamilies
     */
    public function __construct(
        public readonly CompletePackageInterface $composerPackage,
        public readonly ExtensionType $extensionType,
        public readonly ExtensionName $extensionName,
        public readonly string $name,
        public readonly string $version,
        public readonly string|null $downloadUrl,
        public readonly array $configureOptions,
        public readonly bool $supportZts,
        public readonly bool $supportNts,
        public readonly string|null $buildPath,
        public readonly array|null $compatibleOsFamilies,
        public readonly array|null $incompatibleOsFamilies,
    ) {
    }

    public static function fromComposerCompletePackage(CompletePackageInterface $completePackage): self
    {
        $phpExtOptions = $completePackage->getPhpExt();

        $configureOptions = $phpExtOptions !== null && array_key_exists('configure-options', $phpExtOptions)
            ? array_map(
                static fn (array $configureOption): ConfigureOption => ConfigureOption::fromComposerJsonDefinition($configureOption),
                $phpExtOptions['configure-options'],
            )
            : [];

        $supportZts = $phpExtOptions !== null && array_key_exists('support-zts', $phpExtOptions)
            ? $phpExtOptions['support-zts']
            : true;

        $supportNts = $phpExtOptions !== null && array_key_exists('support-nts', $phpExtOptions)
            ? $phpExtOptions['support-nts']
            : true;

        $buildPath = $phpExtOptions !== null && array_key_exists('build-path', $phpExtOptions)
            ? $phpExtOptions['build-path']
            : null;

        $compatibleOsFamilies = $phpExtOptions !== null && array_key_exists('os-families', $phpExtOptions)
            ? $phpExtOptions['os-families']
            : null;

        $incompatibleOsFamilies = $phpExtOptions !== null && array_key_exists('os-families-exclude', $phpExtOptions)
            ? $phpExtOptions['os-families-exclude']
            : null;

        if ($compatibleOsFamilies !== null && $incompatibleOsFamilies !== null) {
            throw new InvalidArgumentException('Cannot specify both "os-families" and "os-families-exclude" in composer.json');
        }

        return new self(
            $completePackage,
            ExtensionType::tryFrom($completePackage->getType()) ?? ExtensionType::PhpModule,
            ExtensionName::determineFromComposerPackage($completePackage),
            $completePackage->getPrettyName(),
            $completePackage->getPrettyVersion(),
            $completePackage->getDistUrl(),
            $configureOptions,
            $supportZts,
            $supportNts,
            $buildPath,
            self::convertInputStringsToOperatingSystemFamilies($compatibleOsFamilies),
            self::convertInputStringsToOperatingSystemFamilies($incompatibleOsFamilies),
        );
    }

    public function prettyNameAndVersion(): string
    {
        return $this->name . ':' . $this->version;
    }

    public function githubOrgAndRepository(): string
    {
        if ($this->downloadUrl === null || str_contains($this->downloadUrl, '/' . $this->name . '/')) {
            return $this->name;
        }

        if (! str_starts_with($this->downloadUrl, 'https://api.github.com/repos/')) {
            return $this->name;
        }

        $parsed = parse_url($this->downloadUrl);
        if ($parsed === false || ! array_key_exists('path', $parsed)) {
            return $this->name;
        }

        // Converts https://api.github.com/repos/<user>/<repository>/zipball/<sha>" to "<user>/<repository>"
        return implode('/', array_slice(explode('/', $parsed['path']), 2, 2));
    }

    /**
     * @param list<string>|null $input
     *
     * @return non-empty-list<OperatingSystemFamily>|null
     */
    private static function convertInputStringsToOperatingSystemFamilies(array|null $input): array|null
    {
        if ($input === null) {
            return null;
        }

        $osFamilies = [];
        foreach ($input as $value) {
            // try to normalize a bit the input
            $valueToTry = ucfirst(strtolower($value));

            Assert::inArray($valueToTry, OperatingSystemFamily::asValuesList(), 'Expected operating system family to be one of: %2$s. Got: %s');

            $osFamilies[] = OperatingSystemFamily::from($valueToTry);
        }

        Assert::isNonEmptyList($osFamilies, 'Expected operating systems families to be a non-empty list.');

        return $osFamilies;
    }
}
