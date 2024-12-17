<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\Ini;

use Composer\Package\CompletePackage;
use Php\Pie\BinaryFile;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\Ini\PickBestSetupIniApproach;
use Php\Pie\Installing\Ini\SetupIniApproach;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(PickBestSetupIniApproach::class)]
final class PickBestSetupIniApproachTest extends TestCase
{
    private function targetPlatform(): TargetPlatform
    {
        return new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            PhpBinaryPath::fromCurrentProcess(),
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
        );
    }

    public function testCannotBeUsedWithoutAnyApproaches(): void
    {
        self::assertFalse((new PickBestSetupIniApproach([]))->canBeUsed($this->targetPlatform()));
    }

    public function testCannotBeUsedWithAnyApproaches(): void
    {
        $one = $this->createMock(SetupIniApproach::class);
        $one->expects(self::once())->method('canBeUsed')->willReturn(false);
        $two = $this->createMock(SetupIniApproach::class);
        $two->expects(self::once())->method('canBeUsed')->willReturn(false);

        self::assertFalse((new PickBestSetupIniApproach([$one, $two]))->canBeUsed($this->targetPlatform()));
    }

    public function testCanBeUsedWithApproachOne(): void
    {
        $one = $this->createMock(SetupIniApproach::class);
        $one->expects(self::once())->method('canBeUsed')->willReturn(false);
        $two = $this->createMock(SetupIniApproach::class);
        $two->expects(self::once())->method('canBeUsed')->willReturn(true);

        self::assertTrue((new PickBestSetupIniApproach([$one, $two]))->canBeUsed($this->targetPlatform()));
    }

    public function testCanBeUsedWithApproachTwo(): void
    {
        $one = $this->createMock(SetupIniApproach::class);
        $one->expects(self::once())->method('canBeUsed')->willReturn(true);
        $two = $this->createMock(SetupIniApproach::class);
        $two->expects(self::once())->method('canBeUsed')->willReturn(false);

        self::assertTrue((new PickBestSetupIniApproach([$one, $two]))->canBeUsed($this->targetPlatform()));
    }

    public function testCanBeUsedWithAllApproaches(): void
    {
        $one = $this->createMock(SetupIniApproach::class);
        $one->expects(self::once())->method('canBeUsed')->willReturn(true);
        $two = $this->createMock(SetupIniApproach::class);
        $two->expects(self::once())->method('canBeUsed')->willReturn(true);

        self::assertTrue((new PickBestSetupIniApproach([$one, $two]))->canBeUsed($this->targetPlatform()));
    }

    public function testVerboseMessageIsEmittedSettingUpWithoutAnyApproaches(): void
    {
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_VERBOSE);

        self::assertFalse((new PickBestSetupIniApproach([]))->setup(
            $this->targetPlatform(),
            DownloadedPackage::fromPackageAndExtractedPath(
                new Package(
                    $this->createMock(CompletePackage::class),
                    ExtensionType::PhpModule,
                    ExtensionName::normaliseFromString('foo'),
                    'test-vendor/test-package',
                    '1.2.3',
                    'https://test-uri/',
                    [],
                    true,
                    true,
                    null,
                    null,
                    null,
                    99,
                ),
                '/path/to/extracted/source',
            ),
            new BinaryFile('/path/to/extracted/source/module/foo.so', 'some-checksum'),
            $output,
        ));

        $outputString = $output->fetch();
        self::assertStringContainsString(
            'No INI setup approaches can be used on this platform.',
            $outputString,
        );
    }

    public function testWorkingApproachIsUsed(): void
    {
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_VERBOSE);

        $one = $this->createMock(SetupIniApproach::class);
        $one->method('canBeUsed')->willReturn(true);
        $one->expects(self::once())->method('setup')->willReturn(false);
        $two = $this->createMock(SetupIniApproach::class);
        $two->method('canBeUsed')->willReturn(true);
        $two->expects(self::once())->method('setup')->willReturn(true);

        self::assertTrue((new PickBestSetupIniApproach([$one, $two]))->setup(
            $this->targetPlatform(),
            DownloadedPackage::fromPackageAndExtractedPath(
                new Package(
                    $this->createMock(CompletePackage::class),
                    ExtensionType::PhpModule,
                    ExtensionName::normaliseFromString('foo'),
                    'test-vendor/test-package',
                    '1.2.3',
                    'https://test-uri/',
                    [],
                    true,
                    true,
                    null,
                    null,
                    null,
                    99,
                ),
                '/path/to/extracted/source',
            ),
            new BinaryFile('/path/to/extracted/source/module/foo.so', 'some-checksum'),
            $output,
        ));

        $outputString = $output->fetch();
        self::assertStringContainsString(
            'Trying to enable extension using MockObject_SetupIniApproach',
            $outputString,
        );
    }

    public function testSetupFailsWhenNoApproachesWork(): void
    {
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_VERBOSE);

        $one = $this->createMock(SetupIniApproach::class);
        $one->method('canBeUsed')->willReturn(true);
        $one->expects(self::once())->method('setup')->willReturn(false);
        $two = $this->createMock(SetupIniApproach::class);
        $two->method('canBeUsed')->willReturn(true);
        $two->expects(self::once())->method('setup')->willReturn(false);

        self::assertFalse((new PickBestSetupIniApproach([$one, $two]))->setup(
            $this->targetPlatform(),
            DownloadedPackage::fromPackageAndExtractedPath(
                new Package(
                    $this->createMock(CompletePackage::class),
                    ExtensionType::PhpModule,
                    ExtensionName::normaliseFromString('foo'),
                    'test-vendor/test-package',
                    '1.2.3',
                    'https://test-uri/',
                    [],
                    true,
                    true,
                    null,
                    null,
                    null,
                    99,
                ),
                '/path/to/extracted/source',
            ),
            new BinaryFile('/path/to/extracted/source/module/foo.so', 'some-checksum'),
            $output,
        ));

        $outputString = $output->fetch();
        self::assertStringContainsString(
            'None of the INI setup approaches succeeded.',
            $outputString,
        );
    }
}
