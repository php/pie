<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\InstallForPhpProject;

use Composer\IO\BufferIO;
use Composer\Package\RootPackage;
use Php\Pie\Command\InvokeSubCommand;
use Php\Pie\ComposerIntegration\PieJsonEditor;
use Php\Pie\Installing\InstallForPhpProject\InstallPiePackageFromPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

#[CoversClass(InstallPiePackageFromPath::class)]
final class InstallPiePackageFromPathTest extends TestCase
{
    private Command&MockObject $command;
    private RootPackage $rootPackage;
    private InvokeSubCommand&MockObject $invokeSubCommand;
    private PieJsonEditor&MockObject $pieJsonEditor;
    private InputInterface&MockObject $input;
    private BufferIO $io;

    public function setUp(): void
    {
        parent::setUp();

        $this->command          = $this->createMock(Command::class);
        $this->rootPackage      = new RootPackage('foo/bar', '1.2.3.0', '1.2.3');
        $this->invokeSubCommand = $this->createMock(InvokeSubCommand::class);
        $this->pieJsonEditor    = $this->createMock(PieJsonEditor::class);
        $this->input            = $this->createMock(InputInterface::class);
        $this->io               = new BufferIO();
    }

    public function testInvokeWithSuccessfulSubCommand(): void
    {
        $this->pieJsonEditor
            ->expects(self::once())
            ->method('ensureExists')
            ->willReturnSelf();
        $this->pieJsonEditor
            ->expects(self::once())
            ->method('addRepository')
            ->with('path', '/path/to/pie/package');
        $this->pieJsonEditor
            ->expects(self::once())
            ->method('removeRepository')
            ->with('/path/to/pie/package');

        $this->invokeSubCommand->expects(self::once())
            ->method('__invoke')
            ->with(
                $this->command,
                [
                    'command' => 'install',
                    'requested-package-and-version' => 'foo/bar:*@dev',
                ],
                $this->input,
            )
            ->willReturn(Command::SUCCESS);

        self::assertSame(
            Command::SUCCESS,
            (new InstallPiePackageFromPath($this->invokeSubCommand))(
                $this->command,
                '/path/to/pie/package',
                $this->rootPackage,
                $this->pieJsonEditor,
                $this->input,
                $this->io,
            ),
        );
    }

    public function testInvokeWithSubCommandException(): void
    {
        $this->pieJsonEditor
            ->expects(self::once())
            ->method('ensureExists')
            ->willReturnSelf();
        $this->pieJsonEditor
            ->expects(self::once())
            ->method('addRepository')
            ->with('path', '/path/to/pie/package');

        // We still expect the package path to be removed even if there is an exception!
        $this->pieJsonEditor
            ->expects(self::once())
            ->method('removeRepository')
            ->with('/path/to/pie/package');

        $this->invokeSubCommand->expects(self::once())
            ->method('__invoke')
            ->with(
                $this->command,
                [
                    'command' => 'install',
                    'requested-package-and-version' => 'foo/bar:*@dev',
                ],
                $this->input,
            )
            ->willThrowException(new RuntimeException('oh no'));

        $install = new InstallPiePackageFromPath($this->invokeSubCommand);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('oh no');
        $install(
            $this->command,
            '/path/to/pie/package',
            $this->rootPackage,
            $this->pieJsonEditor,
            $this->input,
            $this->io,
        );
    }
}
