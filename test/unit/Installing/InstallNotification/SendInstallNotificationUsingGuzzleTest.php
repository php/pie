<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\InstallNotification;

use Composer\Composer;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\InstallNotification\FailedToSendInstallNotification;
use Php\Pie\Installing\InstallNotification\SendInstallNotificationUsingGuzzle;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use Php\Pie\Platform\WindowsCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function function_exists;
use function json_encode;
use function php_uname;
use function sprintf;

#[CoversClass(SendInstallNotificationUsingGuzzle::class)]
final class SendInstallNotificationUsingGuzzleTest extends TestCase
{
    private const FAKE_PHP_VERSION = '6.0.0';

    private ClientInterface&MockObject $client;
    private TargetPlatform $targetPlatform;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(ClientInterface::class);

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('version')
            ->willReturn(self::FAKE_PHP_VERSION);

        $this->targetPlatform = new TargetPlatform(
            OperatingSystem::Windows,
            $phpBinaryPath,
            Architecture::x86,
            ThreadSafetyMode::ThreadSafe,
            1,
            WindowsCompiler::VC14,
        );
    }

    private function downloadedPackageWithNotificationUrl(string|null $notificationUrl): DownloadedPackage
    {
        return DownloadedPackage::fromPackageAndExtractedPath(
            new Package(
                ExtensionType::PhpModule,
                ExtensionName::normaliseFromString('foo'),
                'bar/foo',
                '1.2.3',
                null,
                [],
                $notificationUrl,
                '1.2.3.0',
                true,
                true,
            ),
            '/path/to/extracted',
        );
    }

    public function testNullNotificationUrlDoesNoNotification(): void
    {
        $this->client->expects(self::never())
            ->method('send');

        $sender = new SendInstallNotificationUsingGuzzle($this->client);
        $sender->send(
            $this->targetPlatform,
            $this->downloadedPackageWithNotificationUrl(null),
        );
    }

    public function testSuccessfulPayload(): void
    {
        $this->client->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (RequestInterface $request) {
                self::assertSame('http://example.com/notification', $request->getUri()->__toString());
                self::assertSame('POST', $request->getMethod());
                self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
                self::assertSame(
                    sprintf(
                        'Composer/%s (%s; %s; %s; %s)',
                        Composer::getVersion(),
                        function_exists('php_uname') ? php_uname('s') : 'Unknown',
                        function_exists('php_uname') ? php_uname('r') : 'Unknown',
                        'PHP ' . self::FAKE_PHP_VERSION,
                        'cURL ' . TargetPlatform::getCurlVersion(),
                    ),
                    $request->getHeaderLine('User-Agent'),
                );

                return true;
            }))
            ->willReturn(new Response(
                201,
                [],
                json_encode(['status' => 'success']),
            ));

        $sender = new SendInstallNotificationUsingGuzzle($this->client);
        $sender->send(
            $this->targetPlatform,
            $this->downloadedPackageWithNotificationUrl('http://example.com/notification'),
        );
    }

    public function testPartialSuccessThrowsException(): void
    {
        $this->client->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (RequestInterface $request) {
                self::assertSame('http://example.com/notification', $request->getUri()->__toString());
                self::assertSame('POST', $request->getMethod());
                self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
                self::assertSame(
                    sprintf(
                        'Composer/%s (%s; %s; %s; %s)',
                        Composer::getVersion(),
                        function_exists('php_uname') ? php_uname('s') : 'Unknown',
                        function_exists('php_uname') ? php_uname('r') : 'Unknown',
                        'PHP ' . self::FAKE_PHP_VERSION,
                        'cURL ' . TargetPlatform::getCurlVersion(),
                    ),
                    $request->getHeaderLine('User-Agent'),
                );

                return true;
            }))
            /** @link https://github.com/composer/packagist/blob/fb75c17d75bc032cc88b997275d40077511d0cd9/src/Controller/ApiController.php#L326 */
            ->willReturn(new Response(
                200,
                [],
                json_encode(['status' => 'partial', 'message' => 'Packages (blah) not found']),
            ));

        $sender = new SendInstallNotificationUsingGuzzle($this->client);

        $this->expectException(FailedToSendInstallNotification::class);
        $sender->send(
            $this->targetPlatform,
            $this->downloadedPackageWithNotificationUrl('http://example.com/notification'),
        );
    }
}
