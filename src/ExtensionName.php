<?php

declare(strict_types=1);

namespace Php\Pie;

use Webmozart\Assert\Assert;

use function assert;
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
    /** @link https://github.com/pear/pear-core/blob/6f4c3a0b134626d238d75a44af01a2f7c4e688d9/PEAR/Common.php#L28 */
    private const VALID_PACKAGE_NAME_REGEX = '#^[A-Za-z][a-zA-Z0-9_]+$#';

    // phpcs:disable SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion.RequiredConstructorPropertyPromotion
    /** @var non-empty-string */
    private readonly string $normalisedExtensionName;
    // phpcs:enable

    private function __construct(string $normalisedExtensionName)
    {
        Assert::regex(
            $normalisedExtensionName,
            self::VALID_PACKAGE_NAME_REGEX,
            'The value %s is not a valid extension name. An extension must start with a letter, and only'
            . ' contain alphanumeric characters or underscores',
        );
        assert($normalisedExtensionName !== '');

        $this->normalisedExtensionName = $normalisedExtensionName;
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
