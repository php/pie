<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Package\CompletePackageInterface;
use InvalidArgumentException;
use Php\Pie\ConfigureOption;
use Php\Pie\Downloading\DownloadUrlMethod;
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
use function str_contains;
use function str_starts_with;
use function strtolower;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
final class Package
{
    /** @var list<ConfigureOption> */
    private array $configureOptions = [];
    private int $priority           = 80;
    private string|null $buildPath  = null;
    /** @var non-empty-list<OperatingSystemFamily>|null */
    private array|null $compatibleOsFamilies = null;
    /** @var non-empty-list<OperatingSystemFamily>|null */
    private array|null $incompatibleOsFamilies        = null;
    private bool $supportZts                          = true;
    private bool $supportNts                          = true;
    private DownloadUrlMethod|null $downloadUrlMethod = null;

    public function __construct(
        private readonly CompletePackageInterface $composerPackage,
        private readonly ExtensionType $extensionType,
        private readonly ExtensionName $extensionName,
        private readonly string $name,
        private readonly string $version,
        private readonly string|null $downloadUrl,
    ) {
    }

    public static function fromComposerCompletePackage(CompletePackageInterface $completePackage): self
    {
        $package = new self(
            $completePackage,
            ExtensionType::tryFrom($completePackage->getType()) ?? ExtensionType::PhpModule,
            ExtensionName::determineFromComposerPackage($completePackage),
            $completePackage->getPrettyName(),
            $completePackage->getPrettyVersion(),
            $completePackage->getDistUrl(),
        );

        $phpExtOptions = $completePackage->getPhpExt();

        $package->configureOptions = $phpExtOptions !== null && array_key_exists('configure-options', $phpExtOptions)
            ? array_map(
                static fn (array $configureOption): ConfigureOption => ConfigureOption::fromComposerJsonDefinition($configureOption),
                $phpExtOptions['configure-options'],
            )
            : [];

        $package->supportZts = $phpExtOptions['support-zts'] ?? true;
        $package->supportNts = $phpExtOptions['support-nts'] ?? true;
        $package->buildPath  = $phpExtOptions['build-path'] ?? null;

        $compatibleOsFamilies   = $phpExtOptions['os-families'] ?? null;
        $incompatibleOsFamilies = $phpExtOptions['os-families-exclude'] ?? null;

        if ($compatibleOsFamilies !== null && $incompatibleOsFamilies !== null) {
            throw new InvalidArgumentException('Cannot specify both "os-families" and "os-families-exclude" in composer.json');
        }

        $package->compatibleOsFamilies   = self::convertInputStringsToOperatingSystemFamilies($compatibleOsFamilies);
        $package->incompatibleOsFamilies = self::convertInputStringsToOperatingSystemFamilies($incompatibleOsFamilies);

        $package->priority = $phpExtOptions['priority'] ?? 80;

        if ($phpExtOptions !== null && array_key_exists('download-url-method', $phpExtOptions)) {
            $package->downloadUrlMethod = DownloadUrlMethod::tryFrom($phpExtOptions['download-url-method']);
        }

        return $package;
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

        Assert::isNonEmptyList($input, 'Expected operating systems families to be a non-empty list.');

        return array_map(
            static function ($value): OperatingSystemFamily {
                Assert::inArray(
                    strtolower($value),
                    OperatingSystemFamily::asValuesList(),
                    'Expected operating system family to be one of: %2$s. Got: %s',
                );

                return OperatingSystemFamily::from(strtolower($value));
            },
            $input,
        );
    }

    public function composerPackage(): CompletePackageInterface
    {
        return $this->composerPackage;
    }

    public function extensionType(): ExtensionType
    {
        return $this->extensionType;
    }

    public function extensionName(): ExtensionName
    {
        return $this->extensionName;
    }

    public function isBundledPhpExtension(): bool
    {
        return str_starts_with($this->name(), 'php/');
    }

    public function name(): string
    {
        return $this->name;
    }

    public function version(): string
    {
        return $this->version;
    }

    /** @return list<ConfigureOption> */
    public function configureOptions(): array
    {
        return $this->configureOptions;
    }

    public function downloadUrl(): string|null
    {
        return $this->downloadUrl;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function buildPath(): string|null
    {
        return $this->buildPath;
    }

    /** @return non-empty-list<OperatingSystemFamily>|null */
    public function compatibleOsFamilies(): array|null
    {
        return $this->compatibleOsFamilies;
    }

    /** @return non-empty-list<OperatingSystemFamily>|null */
    public function incompatibleOsFamilies(): array|null
    {
        return $this->incompatibleOsFamilies;
    }

    public function supportZts(): bool
    {
        return $this->supportZts;
    }

    public function supportNts(): bool
    {
        return $this->supportNts;
    }

    public function downloadUrlMethod(): DownloadUrlMethod|null
    {
        return $this->downloadUrlMethod;
    }
}
