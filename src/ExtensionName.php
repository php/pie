<?php

declare(strict_types=1);

namespace Php\Pie;

use Composer\Package\PackageInterface;
use InvalidArgumentException;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function explode;
use function is_string;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
final class ExtensionName
{
    /**
     * PECL extension names must contain only alphanumeric characters and underscores, and must start with an
     * alphabetical character. PIE does not change this requirement for consistency.
     *
     * @link https://github.com/pear/pear-core/blob/6f4c3a0b134626d238d75a44af01a2f7c4e688d9/PEAR/Common.php#L28
     */
    private const VALID_PACKAGE_NAME_REGEX = '#^[A-Za-z][a-zA-Z0-9_]+$#';

    // phpcs:disable SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion.RequiredConstructorPropertyPromotion
    /** @var non-empty-string */
    private readonly string $normalisedExtensionName;
    // phpcs:enable

    private function __construct(string $normalisedExtensionName)
    {
        if (! self::isValidExtensionName($normalisedExtensionName)) {
            throw new InvalidArgumentException(sprintf(
                'The value "%s" is not a valid extension name. An extension must start with a letter, and only contain alphanumeric characters or underscores',
                $normalisedExtensionName,
            ));
        }

        $this->normalisedExtensionName = $normalisedExtensionName;
    }

    /** @phpstan-assert-if-true non-empty-string $extensionName */
    public static function isValidExtensionName(string $extensionName): bool
    {
        return preg_match(self::VALID_PACKAGE_NAME_REGEX, $extensionName) >= 1;
    }

    public static function determineFromComposerPackage(PackageInterface $package): self
    {
        $phpExt = $package->getPhpExt();

        if (
            $phpExt === null
            || ! array_key_exists('extension-name', $phpExt)
            || ! is_string($phpExt['extension-name'])
            || $phpExt['extension-name'] === ''
        ) {
            $packageNameParts = explode('/', $package->getPrettyName());
            Assert::count($packageNameParts, 2, 'Expected a package name like vendor/package for ' . $package->getPrettyName());

            return self::normaliseFromString($packageNameParts[1]);
        }

        return self::normaliseFromString($phpExt['extension-name']);
    }

    public static function normaliseFromString(string $extensionName): self
    {
        if (str_starts_with($extensionName, 'ext-')) {
            return new self(substr($extensionName, strlen('ext-')));
        }

        return new self($extensionName);
    }

    /** @return non-empty-string */
    public function name(): string
    {
        return $this->normalisedExtensionName;
    }

    /** @return non-empty-string */
    public function nameWithExtPrefix(): string
    {
        return 'ext-' . $this->normalisedExtensionName;
    }
}
