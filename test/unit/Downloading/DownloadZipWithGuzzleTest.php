<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Php\Pie\Downloading\DownloadZipWithGuzzle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function mkdir;
use function strlen;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

#[CoversClass(DownloadZipWithGuzzle::class)]
final class DownloadZipWithGuzzleTest extends TestCase
{
    public function testDownloadZipAndReturnLocalPath(): void
    {
        $fakeZipContent = uniqid('fakeZipContent', true);

        $mockHandler = new MockHandler([
            new Response(
                200,
                [
                    'Content-type' => 'application/octet-stream',
                    'Content-length' => (string) (strlen($fakeZipContent)),
                ],
                $fakeZipContent,
            ),
        ]);

        $guzzleMockClient = new Client(['handler' => HandlerStack::create($mockHandler)]);

        $localPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_test_', true);
        mkdir($localPath, 0777, true);
        $downloadedZipFile = (new DownloadZipWithGuzzle($guzzleMockClient))
            ->downloadZipAndReturnLocalPath(
                new Request('GET', 'http://test-uri/'),
                $localPath,
            );

        self::assertSame($fakeZipContent, file_get_contents($downloadedZipFile));
    }
}
