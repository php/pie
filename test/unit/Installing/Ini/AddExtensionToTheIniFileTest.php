<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\Ini;

use Composer\Package\CompletePackage;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\Ini\AddExtensionToTheIniFile;
use Php\Pie\Platform\TargetPhp\Exception\ExtensionIsNotLoaded;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

use function chmod;
use function file_get_contents;
use function file_put_contents;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;
use function touch;
use function unlink;

#[CoversClass(AddExtensionToTheIniFile::class)]
final class AddExtensionToTheIniFileTest extends TestCase
{
    private BufferedOutput $output;
    private PhpBinaryPath&MockObject $mockPhpBinary;

    public function setUp(): void
    {
        parent::setUp();

        $this->output        = new BufferedOutput(BufferedOutput::VERBOSITY_VERBOSE);
        $this->mockPhpBinary = $this->createMock(PhpBinaryPath::class);
    }

    public function testReturnsFalseWhenFileIsNotWritable(): void
    {
        if (TargetPlatform::isRunningAsRoot()) {
            self::markTestSkipped('Test cannot be run as root, as root can always write files');
        }

        $unwritableFilename = tempnam(sys_get_temp_dir(), 'PIE_unwritable_ini_file');
        touch($unwritableFilename);
        chmod($unwritableFilename, 000);

        try {
            self::assertFalse((new AddExtensionToTheIniFile())(
                $unwritableFilename,
                new Package(
                    $this->createMock(CompletePackage::class),
                    ExtensionType::PhpModule,
                    ExtensionName::normaliseFromString('foobar'),
                    'foo/bar',
                    '1.0.0',
                    null,
                    [],
                    true,
                    true,
                    null,
                    null,
                    null,
                ),
                $this->mockPhpBinary,
                $this->output,
            ));

            self::assertStringContainsString(
                sprintf('PHP is configured to use %s, but it is not writable by PIE.', $unwritableFilename),
                $this->output->fetch(),
            );
        } finally {
            chmod($unwritableFilename, 644);
            unlink($unwritableFilename);
        }
    }

    public function testReturnsFalseWhenExistingIniCouldNotBeRead(): void
    {
        if (TargetPlatform::isRunningAsRoot()) {
            self::markTestSkipped('Test cannot be run as root, as root can always read files');
        }

        $unreadableIniFile = tempnam(sys_get_temp_dir(), 'PIE_unreadable_ini_file');
        touch($unreadableIniFile);
        chmod($unreadableIniFile, 222);

        try {
            self::assertFalse((new AddExtensionToTheIniFile())(
                $unreadableIniFile,
                new Package(
                    $this->createMock(CompletePackage::class),
                    ExtensionType::PhpModule,
                    ExtensionName::normaliseFromString('foobar'),
                    'foo/bar',
                    '1.0.0',
                    null,
                    [],
                    true,
                    true,
                    null,
                    null,
                    null,
                ),
                $this->mockPhpBinary,
                $this->output,
            ));

            self::assertStringContainsString(
                sprintf('Tried making a backup of %s but could not read it, aborting enablement of extension', $unreadableIniFile),
                $this->output->fetch(),
            );
        } finally {
            chmod($unreadableIniFile, 644);
            unlink($unreadableIniFile);
        }
    }

    public function testReturnsFalseWhenExtensionWasAddedButPhpRuntimeDidNotLoadExtension(): void
    {
        $extensionName      = ExtensionName::normaliseFromString('foobar');
        $originalIniContent = "; some comment\nerror_reporting=E_ALL\n";

        $iniFile = tempnam(sys_get_temp_dir(), 'PIE_ini_file');
        file_put_contents($iniFile, $originalIniContent);

        /**
         * @psalm-suppress PossiblyNullFunctionCall
         * @psalm-suppress UndefinedThisPropertyAssignment
         */
        (fn () => $this->phpBinaryPath = '/path/to/php')
            ->bindTo($this->mockPhpBinary, PhpBinaryPath::class)();
        $this->mockPhpBinary
            ->expects(self::once())
            ->method('assertExtensionIsLoadedInRuntime')
            ->willThrowException(
                ExtensionIsNotLoaded::fromExpectedExtension($this->mockPhpBinary, $extensionName),
            );

        try {
            self::assertFalse((new AddExtensionToTheIniFile())(
                $iniFile,
                new Package(
                    $this->createMock(CompletePackage::class),
                    ExtensionType::PhpModule,
                    $extensionName,
                    'foo/bar',
                    '1.0.0',
                    null,
                    [],
                    true,
                    true,
                    null,
                    null,
                    null,
                ),
                $this->mockPhpBinary,
                $this->output,
            ));

            self::assertStringContainsString(
                'Something went wrong enabling the foobar extension: Expected extension foobar to be loaded in PHP /path/to/php, but it was not detected.',
                $this->output->fetch(),
            );

            // Ensure the original INI file content was restored
            self::assertSame($originalIniContent, file_get_contents($iniFile));
        } finally {
            unlink($iniFile);
        }
    }

    public function testReturnsTrueWhenExtensionAdded(): void
    {
        $iniFile = tempnam(sys_get_temp_dir(), 'PIE_ini_file');
        touch($iniFile);

        $this->mockPhpBinary
            ->expects(self::once())
            ->method('assertExtensionIsLoadedInRuntime');

        try {
            self::assertTrue((new AddExtensionToTheIniFile())(
                $iniFile,
                new Package(
                    $this->createMock(CompletePackage::class),
                    ExtensionType::PhpModule,
                    ExtensionName::normaliseFromString('foobar'),
                    'foo/bar',
                    '1.0.0',
                    null,
                    [],
                    true,
                    true,
                    null,
                    null,
                    null,
                ),
                $this->mockPhpBinary,
                $this->output,
            ));

            $iniContent = file_get_contents($iniFile);
            self::assertSame("\n; PIE automatically added this to enable the foo/bar extension\nextension=foobar\n", $iniContent);

            self::assertStringContainsString(
                sprintf('Enabled extension foobar in the INI file %s', $iniFile),
                $this->output->fetch(),
            );
        } finally {
            unlink($iniFile);
        }
    }
}
