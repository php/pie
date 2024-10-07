<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\DependencyResolver;

use Php\Pie\Container;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\DependencyResolver\ResolveDependencyWithComposer;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_combine;
use function array_map;

use const PHP_VERSION_ID;

#[CoversClass(ResolveDependencyWithComposer::class)]
final class ResolveDependencyWithComposerTest extends TestCase
{
    private const DOWNLOAD_URL_ANY           = 'https://api.github.com/repos/asgrim/example-pie-extension/zipball/%s';
    private const DOWNLOAD_URL_1_0_1_ALPHA_3 = 'https://api.github.com/repos/asgrim/example-pie-extension/zipball/115f8f8e01ee098a18ec2f47af4852be51ebece7';
    private const DOWNLOAD_URL_1_0_1         = 'https://api.github.com/repos/asgrim/example-pie-extension/zipball/769f906413d6d1e12152f6d34134cbcd347ca253';
    private const DOWNLOAD_URL_1_1_0_BETA_1  = 'https://api.github.com/repos/asgrim/example-pie-extension/zipball/b8cec47269dc607b3111fbebd2c47f5b5112595e';

    /**
     * @return array<non-empty-string|'null', array{0: non-empty-string|null, 1: non-empty-string, 2: non-empty-string}>
     *
     * @psalm-suppress PossiblyUnusedMethod https://github.com/psalm/psalm-plugin-phpunit/issues/131
     */
    public static function validVersionsList(): array
    {
        $versionsAndExpected = [
            [null, '2.0.1', self::DOWNLOAD_URL_ANY],
            ['*', '2.0.1', self::DOWNLOAD_URL_ANY],
            ['dev-main', 'dev-main', self::DOWNLOAD_URL_ANY],
            ['dev-main#769f906413d6d1e12152f6d34134cbcd347ca253', 'dev-main', self::DOWNLOAD_URL_1_0_1],
        ];

        if (PHP_VERSION_ID >= 80300 && PHP_VERSION_ID <= 80300) {
            $versionsAndExpected[] = ['1.0.1-alpha.3@alpha', '1.0.1-alpha.3', self::DOWNLOAD_URL_1_0_1_ALPHA_3];
            $versionsAndExpected[] = ['^1.0', '1.0.1', self::DOWNLOAD_URL_1_0_1];
            $versionsAndExpected[] = ['^1.1.0@alpha', '1.1.0-beta.1', self::DOWNLOAD_URL_1_1_0_BETA_1];
            $versionsAndExpected[] = ['^1.0@beta', '1.0.1', self::DOWNLOAD_URL_1_0_1];
            $versionsAndExpected[] = ['^1.1@beta', '1.1.0-beta.1', self::DOWNLOAD_URL_1_1_0_BETA_1];
            $versionsAndExpected[] = ['~1.0.0', '1.0.1', self::DOWNLOAD_URL_1_0_1];
            $versionsAndExpected[] = ['~1.0.0@alpha', '1.0.1', self::DOWNLOAD_URL_1_0_1];
            $versionsAndExpected[] = ['~1.0.0@beta', '1.0.1', self::DOWNLOAD_URL_1_0_1];
            $versionsAndExpected[] = ['~1.0@beta', '1.0.1', self::DOWNLOAD_URL_1_0_1];
            $versionsAndExpected[] = ['v1.x-dev', 'v1.x-dev', self::DOWNLOAD_URL_1_1_0_BETA_1];
        }

        return array_combine(
            array_map(static fn ($item) => $item[0] ?? 'null', $versionsAndExpected),
            $versionsAndExpected,
        );
    }

    #[DataProvider('validVersionsList')]
    public function testDependenciesAreResolvedToExpectedVersions(
        string|null $requestedVersion,
        string $expectedVersion,
        string $expectedDownloadUrl,
    ): void {
        $container = Container::factory();
        $resolve   = $container->get(DependencyResolver::class);

        $package = $resolve->__invoke(
            TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess()),
            'asgrim/example-pie-extension',
            $requestedVersion,
        );

        self::assertSame($expectedVersion, $package->version);
        self::assertNotNull($package->downloadUrl);
        self::assertStringMatchesFormat($expectedDownloadUrl, $package->downloadUrl);
    }
}
