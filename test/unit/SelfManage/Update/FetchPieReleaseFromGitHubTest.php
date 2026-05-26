<?php

declare(strict_types=1);

namespace Php\PieUnitTest\SelfManage\Update;

use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use Php\Pie\SelfManage\Update\FetchPieReleaseFromGitHub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use Webmozart\Assert\InvalidArgumentException;

use function sprintf;

#[CoversClass(FetchPieReleaseFromGitHub::class)]
final class FetchPieReleaseFromGitHubTest extends TestCase
{
    private HttpDownloader&MockObject $httpDownloader;
    private FetchPieReleaseFromGitHub $fetcher;

    protected function setUp(): void
    {
        $this->httpDownloader = $this->createMock(HttpDownloader::class);
        $this->fetcher        = new FetchPieReleaseFromGitHub('https://api.github.com', $this->httpDownloader);
    }

    #[DataProvider('validBranchProvider')]
    public function testTrunkBranchWithValidBranch(string $branchName): void
    {
        $response = $this->createMock(Response::class);
        $response->method('decodeJson')->willReturn(['default_branch' => $branchName]);

        $this->httpDownloader->expects(self::once())
            ->method('get')
            ->with('https://api.github.com/repos/php/pie')
            ->willReturn($response);

        self::assertSame($branchName, $this->fetcher->trunkBranch());
    }

    /** @return array<string, array{0: string}> */
    public static function validBranchProvider(): array
    {
        return [
            '1.4.x' => ['1.4.x'],
            '1.5.x' => ['1.5.x'],
            '2.0.x' => ['2.0.x'],
            '10.11.x' => ['10.11.x'],
        ];
    }

    /** @param class-string<Throwable> $expectedException */
    #[DataProvider('invalidBranchProvider')]
    public function testTrunkBranchWithInvalidBranch(string $branchName, string $expectedException = RuntimeException::class): void
    {
        $response = $this->createMock(Response::class);
        $response->method('decodeJson')->willReturn(['default_branch' => $branchName]);

        $this->httpDownloader->expects(self::once())
            ->method('get')
            ->with('https://api.github.com/repos/php/pie')
            ->willReturn($response);

        $this->expectException($expectedException);
        if ($expectedException === RuntimeException::class) {
            $this->expectExceptionMessage(sprintf('The default branch "%s" returned by GitHub is not in an expected format.', $branchName));
        }

        $this->fetcher->trunkBranch();
    }

    /** @return array<string, array{0: string, 1?: class-string<Throwable>}> */
    public static function invalidBranchProvider(): array
    {
        return [
            'main'            => ['main'],
            'feature'         => ['feature/security-fix'],
            'version-prefix'  => ['v1.5.x'],
            'no-x-suffix'     => ['1.5.0'],
            'too-many-dots'   => ['1.5.x.y'],
            'empty'           => ['', InvalidArgumentException::class],
            'arbitrary'       => ['some-malicious-branch'],
            'almost-main'     => ['main-fix'],
        ];
    }
}
