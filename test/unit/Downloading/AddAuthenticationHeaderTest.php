<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading;

use Composer\Util\AuthHelper;
use GuzzleHttp\Psr7\Request;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\AddAuthenticationHeader;
use Php\Pie\ExtensionName;
use PHPUnit\Framework\Attributes\CoversClass;
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
            new Package(ExtensionName::normaliseFromString('foo'), 'foo/bar', '1.2.3', $downloadUrl),
            $authHelper,
        );

        self::assertSame('whatever ABC123', $requestWithAuthHeader->getHeaderLine('Authorization'));
    }

    public function testExceptionIsThrownWhenPackageDoesNotHaveDownloadUrl(): void
    {
        $downloadUrl = 'http://test-uri/' . uniqid('path', true);

        $request = new Request('GET', $downloadUrl);

        $authHelper = $this->createMock(AuthHelper::class);

        $addAuthenticationHeader = new AddAuthenticationHeader();
        $package                 = new Package(ExtensionName::normaliseFromString('foo'), 'foo/bar', '1.2.3', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The package foo/bar does not have a download URL');
        $addAuthenticationHeader->withAuthHeaderFromComposer($request, $package, $authHelper);
    }
}
