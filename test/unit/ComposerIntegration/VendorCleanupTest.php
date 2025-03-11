<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration;

use Composer\Composer;
use Composer\Config;
use Composer\Util\Filesystem;
use Php\Pie\ComposerIntegration\VendorCleanup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

use function restore_error_handler;
use function set_error_handler;

use const DIRECTORY_SEPARATOR;
use const E_WARNING;

#[CoversClass(VendorCleanup::class)]
final class VendorCleanupTest extends TestCase
{
    private const VENDOR_DIR = __DIR__ . '/../../assets/vendor-cleanup-dir';

    private OutputInterface&MockObject $output;
    private Filesystem&MockObject $filesystem;
    private VendorCleanup $vendorCleanup;

    public function setUp(): void
    {
        parent::setUp();

        $this->output     = $this->createMock(OutputInterface::class);
        $this->filesystem = $this->createMock(Filesystem::class);

        $this->vendorCleanup = new VendorCleanup($this->output, $this->filesystem);
    }

    private function composerWithVendorDirConfig(string $vendorDirConfig): Composer&MockObject
    {
        $config = $this->createMock(Config::class);
        $config->method('get')
            ->with('vendor-dir')
            ->willReturn($vendorDirConfig);

        $composer = $this->createMock(Composer::class);
        $composer->method('getConfig')
            ->willReturn($config);

        return $composer;
    }

    public function testInvalidVendorDirectory(): void
    {
        $this->output
            ->expects(self::once())
            ->method('writeln')
            ->with(
                '<comment>Vendor directory (vendor-dir config) /path/that/does/not/exist seemed invalid?</comment>',
                OutputInterface::VERBOSITY_VERY_VERBOSE,
            );

        /**
         * scandir will emit a warning in this case, causing phpunit to fail with warning
         *
         * @psalm-suppress InvalidArgument
         */
        set_error_handler(
            static function (): bool|null {
                return null;
            },
            E_WARNING,
        );
        ($this->vendorCleanup)($this->composerWithVendorDirConfig('/path/that/does/not/exist'));
        restore_error_handler();
    }

    public function testVendorDirIsCleaned(): void
    {
        $vendor1Removed = false;
        $vendor2Removed = false;

        $this->filesystem
            ->expects(self::exactly(2))
            ->method('remove')
            ->willReturnCallback(static function (string $path) use (&$vendor1Removed, &$vendor2Removed): bool {
                return match ($path) {
                    self::VENDOR_DIR . DIRECTORY_SEPARATOR . 'vendor1' => $vendor1Removed = true,
                    self::VENDOR_DIR . DIRECTORY_SEPARATOR . 'vendor2' => $vendor2Removed = true,
                };
            });

        ($this->vendorCleanup)($this->composerWithVendorDirConfig(self::VENDOR_DIR));

        self::assertTrue($vendor1Removed);
        self::assertTrue($vendor2Removed);
    }
}
