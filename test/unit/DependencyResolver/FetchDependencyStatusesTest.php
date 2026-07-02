<?php

declare(strict_types=1);

namespace Php\PieUnitTest\DependencyResolver;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\VersionParser;
use Php\Pie\DependencyResolver\FetchDependencyStatuses;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function assert;

use const PHP_MAJOR_VERSION;
use const PHP_MINOR_VERSION;
use const PHP_RELEASE_VERSION;
use const PHP_VERSION;

#[CoversClass(FetchDependencyStatuses::class)]
final class FetchDependencyStatusesTest extends TestCase
{
    public function testNoRequiresReturnsEmptyArray(): void
    {
        $package = new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3');

        self::assertEquals([], (new FetchDependencyStatuses())(TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess(), null, null), $this->createMock(Composer::class), $package));
    }

    /** @return array<non-empty-string, array{0: non-empty-string, 1: non-empty-string}> */
    public static function phpVersionProvider(): array
    {
        return [
            '8.2.0' => ['8.2.0', '8.2.0'],
            '8.2.0-dev' => ['8.2.0', '8.2.0-dev'],
            '8.2.0-alpha' => ['8.2.0', '8.2.0-alpha'],
            '8.2.0-RC1' => ['8.2.0', '8.2.0-RC1'],
            PHP_VERSION => [PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION, PHP_VERSION],
        ];
    }

    #[DataProvider('phpVersionProvider')]
    public function testRequiresReturnsListOfStatuses(string $version, string $versionWithExtra): void
    {
        $php = $this->createMock(PhpBinaryPath::class);
        $php->method('operatingSystem')->willReturn(OperatingSystem::NonWindows);
        $php->method('operatingSystemFamily')->willReturn(OperatingSystemFamily::Linux);
        $php->method('machineType')->willReturn(Architecture::x86_64);
        $php->expects(self::any())
            ->method('version')
            ->willReturn($version);
        $php->expects(self::any())
            ->method('phpVersionWithExtra')
            ->willReturn($versionWithExtra);
        $php->expects(self::any())
            ->method('extensions')
            ->willReturn(['Core' => $versionWithExtra, 'standard' => $versionWithExtra]);

        $versionParser    = new VersionParser();
        $parsedPhpVersion = $versionParser->parseConstraints($php->phpVersionWithExtra());
        assert($parsedPhpVersion instanceof Constraint);

        $package = new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3');
        $package->setRequires([
            'ext-core' => new Link('__root__', 'ext-core', $versionParser->parseConstraints('= ' . $php->phpVersionWithExtra())),
            'ext-nonsense_extension' => new Link('__root__', 'ext-nonsense_extension', $versionParser->parseConstraints('*')),
            'ext-standard' => new Link('__root__', 'ext-standard', $versionParser->parseConstraints('< 1.0.0')),
        ]);

        $deps = (new FetchDependencyStatuses())(
            TargetPlatform::fromPhpBinaryPath($php, null, null),
            Factory::create($this->createMock(IOInterface::class)),
            $package,
        );

        self::assertCount(3, $deps);

        self::assertSame('ext-core: = ' . $php->phpVersionWithExtra() . ' ✅', $deps[0]->asPrettyString());
        self::assertSame('ext-nonsense_extension: * 🚫 (not installed)', $deps[1]->asPrettyString());
        self::assertSame('ext-standard: < 1.0.0 🚫 (your version is ' . $parsedPhpVersion->getVersion() . ')', $deps[2]->asPrettyString());
    }
}
