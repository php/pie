<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\Ini;

use Composer\Package\CompletePackageInterface;
use Php\Pie\BinaryFile;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\Ini\PreCheckExtensionAlreadyLoaded;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\Exception\ExtensionIsNotLoaded;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(PreCheckExtensionAlreadyLoaded::class)]
final class PreCheckExtensionAlreadyLoadedTest extends TestCase
{
    private BufferedOutput $output;
    private PhpBinaryPath&MockObject $mockPhpBinary;
    private TargetPlatform $targetPlatform;
    private DownloadedPackage $downloadedPackage;
    private BinaryFile $binaryFile;
    private PreCheckExtensionAlreadyLoaded $preCheckExtensionAlreadyLoaded;

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
                [],
                true,
                true,
                null,
                null,
                null,
                99,
            ),
            '/path/to/extracted/source',
        );

        $this->binaryFile = new BinaryFile('/path/to/compiled/extension.so', 'fake checksum');

        $this->preCheckExtensionAlreadyLoaded = new PreCheckExtensionAlreadyLoaded();
    }

    public function testCanBeUsed(): void
    {
        self::assertTrue($this->preCheckExtensionAlreadyLoaded->canBeUsed(
            $this->targetPlatform,
        ));
    }

    public function testSetupReturnsTrueWhenExtAlreadyRuntimeLoaded(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('assertExtensionIsLoadedInRuntime')
            ->with($this->downloadedPackage->package->extensionName, $this->output);

        self::assertTrue($this->preCheckExtensionAlreadyLoaded->setup(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $this->output,
        ));
    }

    public function testSetupReturnsFalseWhenExtIsNotRuntimeLoaded(): void
    {
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('assertExtensionIsLoadedInRuntime')
            ->with($this->downloadedPackage->package->extensionName, $this->output)
            ->willThrowException(ExtensionIsNotLoaded::fromExpectedExtension(
                $this->mockPhpBinary,
                $this->downloadedPackage->package->extensionName,
            ));

        self::assertFalse($this->preCheckExtensionAlreadyLoaded->setup(
            $this->targetPlatform,
            $this->downloadedPackage,
            $this->binaryFile,
            $this->output,
        ));
    }
}
