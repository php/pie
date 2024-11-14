<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading;

use Php\Pie\Downloading\ExtractZip;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

use function extension_loaded;
use function file_get_contents;
use function mkdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const DIRECTORY_SEPARATOR;

#[CoversClass(ExtractZip::class)]
final class ExtractZipTest extends TestCase
{
    public function testZipFileCanBeExtracted(): void
    {
        $localPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_test_', true);
        mkdir($localPath, 0777, true);

        $extr = new ExtractZip();

        $extractedPath = $extr->to(__DIR__ . '/../../assets/test-zip.zip', $localPath);

        // The test-zip.zip should contain a deterministic file content for this:
        self::assertSame('Hello there! Test UUID b925c59b-3e6f-4e45-8029-19431df18de4', file_get_contents($extractedPath . DIRECTORY_SEPARATOR . 'test-file.txt'));

        unlink($localPath);
    }

    #[RequiresPhpExtension('zip')]
    public function testFailureToExtractZipWithInvalidZip(): void
    {
        $localPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_test_DO_NOT_EXIST_', true);

        $extr = new ExtractZip();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Could not open ZIP [%d]: %s', ZipArchive::ER_NOZIP, __FILE__));
        $extr->to(__FILE__, $localPath);
    }

    public function testFailureToExtractZipWithInvalidZipAndPharData(): void
    {
        if (extension_loaded('zip')) {
            $this->markTestSkipped('This test can only run when "ext-zip" is not loaded.');
        }

        $localPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_test_DO_NOT_EXIST_', true);

        $extr = new ExtractZip();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not extract ZIP "' . __FILE__ . '" to path: ' . $localPath);
        $extr->to(__FILE__, $localPath);
    }
}
