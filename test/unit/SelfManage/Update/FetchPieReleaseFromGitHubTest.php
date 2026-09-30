<?php

declare(strict_types=1);

namespace Php\PieUnitTest\SelfManage\Update;

use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use Php\Pie\SelfManage\Update\Channel;
use Php\Pie\SelfManage\Update\FetchPieReleaseFromGitHub;
use Php\Pie\SelfManage\Update\PieAssetName;
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
        $this->fetcher        = new FetchPieReleaseFromGitHub('https://api.github.com', $this->httpDownloader, PieAssetName::Phar);
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

    public function testLatestReleaseMetadataSelectsTheExecutableAsset(): void
    {
        $response = $this->createMock(Response::class);
        $response->method('decodeJson')->willReturn([
            [
                'tag_name' => '1.5.1',
                'assets' => [
                    ['name' => 'pie.phar', 'browser_download_url' => 'https://github.com/php/pie/releases/download/1.5.1/pie.phar'],
                    ['name' => 'pie-Linux-ARM64', 'browser_download_url' => 'https://github.com/php/pie/releases/download/1.5.1/pie-Linux-ARM64'],
                    ['name' => 'pie-Linux-X64', 'browser_download_url' => 'https://github.com/php/pie/releases/download/1.5.1/pie-Linux-X64'],
                    ['name' => 'pie-macOS-ARM64', 'browser_download_url' => 'https://github.com/php/pie/releases/download/1.5.1/pie-macOS-ARM64'],
                    ['name' => 'pie-Windows-X64.exe', 'browser_download_url' => 'https://github.com/php/pie/releases/download/1.5.1/pie-Windows-X64.exe'],
                ],
            ],
        ]);

        $this->httpDownloader->expects(self::once())
            ->method('get')
            ->with('https://api.github.com/repos/php/pie/releases')
            ->willReturn($response);

        $release = (new FetchPieReleaseFromGitHub('https://api.github.com', $this->httpDownloader, PieAssetName::LinuxX64))
            ->latestReleaseMetadata(Channel::Stable);

        self::assertSame('1.5.1', $release->tag);
        self::assertSame('https://github.com/php/pie/releases/download/1.5.1/pie-Linux-X64', $release->downloadUrl);
    }
}
