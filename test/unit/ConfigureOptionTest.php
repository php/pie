<?php

declare(strict_types=1);

namespace Php\PieUnitTest;

use InvalidArgumentException;
use Php\Pie\ConfigureOption;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigureOption::class)]
final class ConfigureOptionTest extends TestCase
{
    /**
     * @return array<
     *     non-empty-string,
     *     array{
     *         definition: array<array-key, mixed>,
     *         expectedName: string,
     *         expectedNeedsValue: bool,
     *         expectedDescription: string,
     *     }
     * >
     *
     * @psalm-suppress PossiblyUnusedMethod https://github.com/psalm/psalm-plugin-phpunit/issues/131
     */
    public function composerJsonDefinitions(): array
    {
        return [
            'minimal' => [
                'definition' => ['name' => 'foo'],
                'expectedName' => 'foo',
                'expectedNeedsValue' => false,
                'expectedDescription' => '',
            ],
            'full' => [
                'definition' => [
                    'name' => 'foo',
                    'needs-value' => false,
                    'description' => 'Some option',
                ],
                'expectedName' => 'foo',
                'expectedNeedsValue' => false,
                'expectedDescription' => 'Some option',
            ],
            'needs-value' => [
                'definition' => [
                    'name' => 'foo',
                    'needs-value' => true,
                    'description' => 'Some option',
                ],
                'expectedName' => 'foo',
                'expectedNeedsValue' => true,
                'expectedDescription' => 'Some option',
            ],
            'empty-description-allowed' => [
                'definition' => [
                    'name' => 'foo',
                    'needs-value' => false,
                    'description' => '',
                ],
                'expectedName' => 'foo',
                'expectedNeedsValue' => false,
                'expectedDescription' => '',
            ],
        ];
    }

    /** @param array<array-key, mixed> $definition */
    #[DataProvider('composerJsonDefinitions')]
    public function testCanBeConstructedFromValidComposerJsonDefinition(array $definition, string $expectedName, bool $expectedNeedsValue, string $expectedDescription): void
    {
        $configureOption = ConfigureOption::fromComposerJsonDefinition($definition);

        self::assertSame($expectedName, $configureOption->name);
        self::assertSame($expectedNeedsValue, $configureOption->needsValue);
        self::assertSame($expectedDescription, $configureOption->description);
    }

    /**
     * @return array<
     *     non-empty-string,
     *     array{
     *         definition: array<array-key, mixed>
     *     }
     * >
     *
     * @psalm-suppress PossiblyUnusedMethod https://github.com/psalm/psalm-plugin-phpunit/issues/131
     */
    public function invalidComposerJsonDefinitions(): array
    {
        return [
            'no-keys' => [
                'definition' => [],
            ],
            'empty-name' => [
                'definition' => ['name' => ''],
            ],
            'needs-value-invalid-type' => [
                'definition' => ['name' => 'foo', 'needs-value' => 'true'],
            ],
            'description-invalid-type' => [
                'definition' => ['name' => 'foo', 'description' => 123],
            ],
        ];
    }

    /** @param array<array-key, mixed> $definition */
    #[DataProvider('invalidComposerJsonDefinitions')]
    public function testInvalidComposerJsonDefinitionsAreRejected(array $definition): void
    {
        $this->expectException(InvalidArgumentException::class);
        ConfigureOption::fromComposerJsonDefinition($definition);
    }
}
