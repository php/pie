<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration;

use Php\Pie\ComposerIntegration\PieJsonEditor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function sys_get_temp_dir;
use function trim;
use function uniqid;

use const DIRECTORY_SEPARATOR;

#[CoversClass(PieJsonEditor::class)]
final class PieJsonEditorTest extends TestCase
{
    public function testCreatingPieJson(): void
    {
        $testPieJson = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_json_test_', true) . '.json';

        self::assertFileDoesNotExist($testPieJson);

        (new PieJsonEditor($testPieJson))->ensureExists();

        self::assertFileExists($testPieJson);
        self::assertSame("{\n}\n", file_get_contents($testPieJson));
    }

    public function testCanAddRequire(): void
    {
        $testPieJson = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_json_test_', true) . '.json';

        $editor = new PieJsonEditor($testPieJson);
        $editor->ensureExists();

        $editor->addRequire('foo/bar', '^1.2');
        self::assertSame(
            <<<'EOF'
            {
                "require": {
                    "foo/bar": "^1.2"
                }
            }
            EOF,
            trim(file_get_contents($testPieJson)),
        );
    }

    public function testCanRevert(): void
    {
        $testPieJson = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_json_test_', true) . '.json';

        $editor = new PieJsonEditor($testPieJson);
        $editor->ensureExists();
        $originalContent = $editor->addRequire('foo/bar', '^1.2');
        $editor->revert($originalContent);
        self::assertSame($originalContent, file_get_contents($testPieJson));
    }

    public function testCanAddAndRemoveRepositories(): void
    {
        $testPieJson = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_json_test_', true) . '.json';

        $editor = new PieJsonEditor($testPieJson);
        $editor->ensureExists();

        $originalContent = $editor->addRepository(
            'vcs',
            'https://github.com/php/pie',
        );

        self::assertSame("{\n}\n", $originalContent);

        $expectedRepoContent = <<<'EOF'
            {
                "repositories": {
                    "https://github.com/php/pie": {
                        "type": "vcs",
                        "url": "https://github.com/php/pie"
                    }
                }
            }
            EOF;

        self::assertSame($expectedRepoContent, trim(file_get_contents($testPieJson)));

        $originalContent2 = $editor->removeRepository('https://github.com/php/pie');
        self::assertSame($expectedRepoContent, trim($originalContent2));

        self::assertSame(
            <<<'EOF'
            {
                "repositories": {
                }
            }
            EOF,
            trim(file_get_contents($testPieJson)),
        );
    }
}
