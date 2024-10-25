<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading;

use Composer\Package\CompletePackage;
use Composer\Util\AuthHelper;
use Generator;
use GuzzleHttp\Psr7\Request;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\AddAuthenticationHeader;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function uniqid;

#[CoversClass(AddAuthenticationHeader::class)]
final class AddAuthenticationHeaderTest extends TestCase
{
    public function testAuthorizationHeaderIsAdded(): void
    {
        $downloadUrl = 'http://test-uri/' . uniqid('path', true);

        $request = new Request('GET', $downloadUrl);

        $authHelper = $this->createMock(AuthHelper::class);
        $authHelper->expects(self::once())
            ->method('addAuthenticationHeader')
            ->with([], 'github.com', $downloadUrl)
            ->willReturn(['Authorization: whatever ABC123']);

        $requestWithAuthHeader = (new AddAuthenticationHeader())->withAuthHeaderFromComposer(
            $request,
            $this->createDummyPackage($downloadUrl),
            $authHelper,
        );

        self::assertSame('whatever ABC123', $requestWithAuthHeader->getHeaderLine('Authorization'));
    }

    #[DataProvider('provideInvalidAuthorizationHeaders')]
    public function testEmptyValueInAuthorizationHeaderThrowsException(string $rawHeader): void
    {
        $downloadUrl = 'http://test-uri/' . uniqid('path', true);

        $request = new Request('GET', $downloadUrl);

        $authHelper = $this->createMock(AuthHelper::class);
        $authHelper->expects(self::once())
            ->method('addAuthenticationHeader')
            ->with([], 'github.com', $downloadUrl)
            ->willReturn([$rawHeader]);

        $addAuthenticationHeader = new AddAuthenticationHeader();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Authorization header is malformed, it should contain a non-empty key and a non-empty value.');
        $addAuthenticationHeader->withAuthHeaderFromComposer($request, $this->createDummyPackage($downloadUrl), $authHelper);
    }

    /**
     * @return Generator<string[]>
     *
     * @psalm-suppress PossiblyUnusedMethod https://github.com/psalm/psalm-plugin-phpunit/issues/131
     */
    public static function provideInvalidAuthorizationHeaders(): Generator
    {
        yield ['Authorization:'];
        yield [': Bearer'];
        yield [' : Bearer'];
        yield ['Authorization: '];
        yield [':'];
        yield ['Authorization MyToken'];
    }

    public function testExceptionIsThrownWhenPackageDoesNotHaveDownloadUrl(): void
    {
        $downloadUrl = 'http://test-uri/' . uniqid('path', true);

        $request = new Request('GET', $downloadUrl);

        $authHelper = $this->createMock(AuthHelper::class);

        $addAuthenticationHeader = new AddAuthenticationHeader();
        $package                 = $this->createDummyPackage();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The package foo/bar does not have a download URL');
        $addAuthenticationHeader->withAuthHeaderFromComposer($request, $package, $authHelper);
    }

    private function createDummyPackage(string|null $downloadUrl = null): Package
    {
        return new Package(
            $this->createMock(CompletePackage::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'foo/bar',
            '1.2.3',
            $downloadUrl,
            [],
            null,
            '1.2.3.0',
            true,
            true,
        );
    }
}
