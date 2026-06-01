<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Package\CompletePackageInterface;

use function array_key_exists;
use function is_string;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @phpstan-type PieMetadata = array{
 *     pie-target-platform-php-path?: non-empty-string,
 *     pie-target-platform-php-config-path?: non-empty-string,
 *     pie-target-platform-php-version?: non-empty-string,
 *     pie-target-platform-php-thread-safety?: non-empty-string,
 *     pie-target-platform-php-windows-compiler?: non-empty-string,
 *     pie-target-platform-architecture?: non-empty-string,
 *     pie-configure-options?: non-empty-string,
 *     pie-built-binary?: non-empty-string,
 *     pie-installed-binary-checksum?: non-empty-string,
 *     pie-installed-binary?: non-empty-string,
 *     pie-phpize-binary?: non-empty-string,
 * }
 */
final class InstalledJsonMetadata
{
    public const KEY_TARGET_PLATFORM_PHP_PATH             = 'pie-target-platform-php-path';
    public const KEY_TARGET_PLATFORM_PHP_CONFIG_PATH      = 'pie-target-platform-php-config-path';
    public const KEY_TARGET_PLATFORM_PHP_VERSION          = 'pie-target-platform-php-version';
    public const KEY_TARGET_PLATFORM_PHP_THREAD_SAFETY    = 'pie-target-platform-php-thread-safety';
    public const KEY_TARGET_PLATFORM_PHP_WINDOWS_COMPILER = 'pie-target-platform-php-windows-compiler';
    public const KEY_TARGET_PLATFORM_ARCHITECTURE         = 'pie-target-platform-architecture';
    public const KEY_CONFIGURE_OPTIONS                    = 'pie-configure-options';
    public const KEY_BUILT_BINARY                         = 'pie-built-binary';
    public const KEY_BINARY_CHECKSUM                      = 'pie-installed-binary-checksum';
    public const KEY_INSTALLED_BINARY                     = 'pie-installed-binary';
    public const KEY_PHPIZE_BINARY                        = 'pie-phpize-binary';

    private const ALL_KEYS = [
        self::KEY_TARGET_PLATFORM_PHP_PATH,
        self::KEY_TARGET_PLATFORM_PHP_CONFIG_PATH,
        self::KEY_TARGET_PLATFORM_PHP_VERSION,
        self::KEY_TARGET_PLATFORM_PHP_THREAD_SAFETY,
        self::KEY_TARGET_PLATFORM_PHP_WINDOWS_COMPILER,
        self::KEY_TARGET_PLATFORM_ARCHITECTURE,
        self::KEY_CONFIGURE_OPTIONS,
        self::KEY_BUILT_BINARY,
        self::KEY_BINARY_CHECKSUM,
        self::KEY_INSTALLED_BINARY,
        self::KEY_PHPIZE_BINARY,
    ];

    /** @param PieMetadata $values */
    private function __construct(private readonly array $values)
    {
    }

    /** @param PieMetadata $values */
    public static function fromArray(array $values): self
    {
        return new self($values);
    }

    public static function fromComposerPackage(CompletePackageInterface $composerPackage): self
    {
        $composerPackageExtras = $composerPackage->getExtra();

        $onlyPieExtras = [];

        foreach (self::ALL_KEYS as $key) {
            if (
                ! array_key_exists($key, $composerPackageExtras)
                || ! is_string($composerPackageExtras[$key])
                || $composerPackageExtras[$key] === ''
            ) {
                continue;
            }

            $onlyPieExtras[$key] = $composerPackageExtras[$key];
        }

        return new self($onlyPieExtras);
    }

    /** @return PieMetadata */
    public function all(): array
    {
        return $this->values;
    }

    /** @return non-empty-string|null */
    private function nonEmptyStringOrNull(string $key): string|null
    {
        return array_key_exists($key, $this->values)
            ? $this->values[$key]
            : null;
    }

    /** @return non-empty-string|null */
    public function targetPlatformPhpPath(): string|null
    {
        return $this->nonEmptyStringOrNull(self::KEY_TARGET_PLATFORM_PHP_PATH);
    }

    /** @return non-empty-string|null */
    public function targetPlatformPhpConfigPath(): string|null
    {
        return $this->nonEmptyStringOrNull(self::KEY_TARGET_PLATFORM_PHP_CONFIG_PATH);
    }

    /** @return non-empty-string|null */
    public function targetPlatformPhpVersion(): string|null
    {
        return $this->nonEmptyStringOrNull(self::KEY_TARGET_PLATFORM_PHP_VERSION);
    }

    /** @return non-empty-string|null */
    public function targetPlatformPhpThreadSafety(): string|null
    {
        return $this->nonEmptyStringOrNull(self::KEY_TARGET_PLATFORM_PHP_THREAD_SAFETY);
    }

    /** @return non-empty-string|null */
    public function targetPlatformPhpWindowsCompiler(): string|null
    {
        return $this->nonEmptyStringOrNull(self::KEY_TARGET_PLATFORM_PHP_WINDOWS_COMPILER);
    }

    /** @return non-empty-string|null */
    public function targetPlatformArchitecture(): string|null
    {
        return $this->nonEmptyStringOrNull(self::KEY_TARGET_PLATFORM_ARCHITECTURE);
    }

    /** @return non-empty-string|null */
    public function configureOptions(): string|null
    {
        return $this->nonEmptyStringOrNull(self::KEY_CONFIGURE_OPTIONS);
    }

    /** @return non-empty-string|null */
    public function builtBinary(): string|null
    {
        return $this->nonEmptyStringOrNull(self::KEY_BUILT_BINARY);
    }

    /** @return non-empty-string|null */
    public function binaryChecksum(): string|null
    {
        return $this->nonEmptyStringOrNull(self::KEY_BINARY_CHECKSUM);
    }

    /** @return non-empty-string|null */
    public function installedBinary(): string|null
    {
        return $this->nonEmptyStringOrNull(self::KEY_INSTALLED_BINARY);
    }

    /** @return non-empty-string|null */
    public function phpizeBinary(): string|null
    {
        return $this->nonEmptyStringOrNull(self::KEY_PHPIZE_BINARY);
    }

    /** Has this package been downloaded, according to the metadata? (note: does not verifiy it is STILL downloaded - especially if vendor cleanup happened!) */
    public function isDownloaded(): bool
    {
        return $this->targetPlatformPhpVersion() !== null;
    }

    /** Has this package been built, according to the metadata? (note: does not verify it is STILL built) */
    public function isBuilt(): bool
    {
        return $this->isDownloaded() && $this->builtBinary() !== null;
    }

    /** Has this package been installed, according to the metadata (note: not verify it is STILL installed/verified) */
    public function isInstalled(): bool
    {
        return $this->isBuilt() && $this->installedBinary() !== null;
    }
}
