<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\Ini;

use Composer\Package\CompletePackageInterface;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\File\BinaryFile;
use Php\Pie\Installing\Ini\DockerPhpExtEnable;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\Exception\ExtensionIsNotLoaded;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[RequiresOperatingSystemFamily('Linux')]
#[CoversClass(DockerPhpExtEnable::class)]
final class DockerPhpExtEnableTest extends TestCase
{
    private const NON_EXISTENT_DOCKER_PHP_EXT_ENABLE = 'something-that-should-not-be-in-path';
    private const GOOD_DOCKER_PHP_EXT_ENABLE         = __DIR__ . '/../../../assets/docker-php-ext-enable/good';
    private const BAD_DOCKER_PHP_EXT_ENABLE          = __DIR__ . '/../../../assets/docker-php-ext-enable/bad';

    private BufferedOutput $output;
    private PhpBinaryPath&MockObject $mockPhpBinary;
    private TargetPlatform $targetPlatform;
    private DownloadedPackage $downloadedPackage;
    private BinaryFile $binaryFile;

    public function setUp(): void
    {
        parent::setUp();

        $this->output = new BufferedOutput(BufferedOutput::VERBOSITY_VERBOSE);

        $this->mockPhpBinary = $this->createMock(PhpBinaryPath::class);
        /**
         * @psalm-suppress PossiblyNullFunctionCall
         * @psalm-suppress UndefinedThisPropertyAssignment
         */
        (fn () => $this->phpBinaryPath = '/path/to/php')
            ->bindTo($this->mockPhpBinary, PhpBinaryPath::class)();

        $this->targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $this->mockPhpBinary,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
        );

        $this->downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            new Package(
                $this->createMock(CompletePackageInterface::class),
                ExtensionType::PhpModule,
                ExtensionName::normaliseFromString('foobar'),
                'foo/bar',
                '1.2.3',
                null,
            ),
            '/path/to/extracted/source',
        );

        $this->binaryFile = new BinaryFile('/path/to/compiled/extension.so', 'fake checksum');
    }

    public function testCannotBeUsedWhenDockerPhpExtEnableIsNotInPath(): void
    {
        self::assertFalse(
            (new DockerPhpExtEnable(self::NON_EXISTENT_DOCKER_PHP_EXT_ENABLE))
                ->canBeUsed($this->targetPlatform),
        );
    }

    public function testCanBeUsedWhenDockerPhpExtEnableIsInPath(): void
    {
        self::assertTrue(
            (new DockerPhpExtEnable(self::GOOD_DOCKER_PHP_EXT_ENABLE))
                ->canBeUsed($this->targetPlatform),
        );
    }

    public function testSetupReturnsFalseWhenWhenDockerPhpExtEnableIsNotInPath(): void
    {
        $this->mockPhpBinary
            ->expects(self::never())
            ->method('assertExtensionIsLoadedInRuntime');

        self::assertFalse(
            (new DockerPhpExtEnable(self::NON_EXISTENT_DOCKER_PHP_EXT_ENABLE))
                ->setup(
                    $this->targetPlatform,
                    $this->downloadedPackage,
                    $this->binaryFile,
                    $this->output,
                ),
        );
    }

    public function testReturnsTrueWhenDockerPhpExtEnableSuccessfullyEnablesExtension(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('assertExtensionIsLoadedInRuntime')
            ->with($this->downloadedPackage->package->extensionName(), $this->output);

        self::assertTrue(
            (new DockerPhpExtEnable(self::GOOD_DOCKER_PHP_EXT_ENABLE))
                ->setup(
                    $this->targetPlatform,
                    $this->downloadedPackage,
                    $this->binaryFile,
                    $this->output,
                ),
        );
    }

    public function testReturnsFalseWhenDockerPhpExtEnableFailsToBeRun(): void
    {
        $this->mockPhpBinary
            ->expects(self::never())
            ->method('assertExtensionIsLoadedInRuntime');

        self::assertFalse(
            (new DockerPhpExtEnable(self::BAD_DOCKER_PHP_EXT_ENABLE))
                ->setup(
                    $this->targetPlatform,
                    $this->downloadedPackage,
                    $this->binaryFile,
                    $this->output,
                ),
        );
    }

    public function testReturnsFalseWhenDockerPhpExtEnableFailsToAssertExtensionWasEnabled(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('assertExtensionIsLoadedInRuntime')
            ->with($this->downloadedPackage->package->extensionName(), $this->output)
            ->willThrowException(ExtensionIsNotLoaded::fromExpectedExtension(
                $this->mockPhpBinary,
                $this->downloadedPackage->package->extensionName(),
            ));

        self::assertFalse(
            (new DockerPhpExtEnable(self::GOOD_DOCKER_PHP_EXT_ENABLE))
                ->setup(
                    $this->targetPlatform,
                    $this->downloadedPackage,
                    $this->binaryFile,
                    $this->output,
                ),
        );
    }
}
