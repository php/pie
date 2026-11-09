<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\ComposerIntegration;

use Composer\IO\NullIO;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\Container;
use Php\Pie\Platform;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Util\PieVersion;
use Php\PieIntegrationTest\Command\IsolatedWorkingDirectoryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function sprintf;
use function strtoupper;

use const PHP_MAJOR_VERSION;
use const PHP_MINOR_VERSION;
use const PHP_RELEASE_VERSION;

#[CoversClass(PieComposerFactory::class)]
final class PieComposerFactoryTest extends IsolatedWorkingDirectoryTestCase
{
    public function testHttpRequestsUsePieUserAgent(): void
    {
        $targetPlatform = TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess(), null, null);

        $composer = PieComposerFactory::createPieComposer(
            Container::testFactory(),
            PieComposerRequest::noOperation(new NullIO(), $targetPlatform),
        );

        $httpOptions = $composer->getLoop()->getHttpDownloader()->getOptions()['http'] ?? null;
        self::assertIsArray($httpOptions);
        self::assertArrayHasKey('header', $httpOptions);
        self::assertIsArray($httpOptions['header']);

        // Example: User-Agent: PIE/1.6.0 (linux; x86_64; Target-PHP 8.4.19 TS; Runner-PHP 8.4.19; phar)
        self::assertSame(
            sprintf(
                'User-Agent: PIE/%s (%s; %s; Target-PHP %s %s; Runner-PHP %s; %s)',
                PieVersion::get(),
                $targetPlatform->operatingSystemFamily->value,
                $targetPlatform->architecture->name,
                $targetPlatform->phpBinaryPath->version(),
                strtoupper($targetPlatform->threadSafety->asShort()),
                PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION,
                Platform::isRunningStaticPhp() ? 'static-php' : 'phar',
            ),
            $httpOptions['header']['user-agent'] ?? null,
        );
    }
}
