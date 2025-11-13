<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration;

use Php\Pie\ComposerIntegration\PieJsonEditor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

#[CoversClass(PieJsonEditor::class)]
final class PieJsonEditorTest extends TestCase
{
    public function testCreatingPieJson(): void
    {
        $testPieJson = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_json_test_', true) . '.json';

        self::assertFileDoesNotExist($testPieJson);

        (new PieJsonEditor($testPieJson, dirname($testPieJson)))->ensureExists();

        self::assertFileExists($testPieJson);
        self::assertSame(
            $this->normaliseJson("{\n}\n"),
            $this->normaliseJson(file_get_contents($testPieJson)),
        );
    }

    public function testCanReadOldRepositoryConfiguration(): void
    {
        $testPieJson = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_json_test_', true) . '.json';

        $editor = new PieJsonEditor($testPieJson, dirname($testPieJson));
        $editor->ensureExists();
        file_put_contents(
            $testPieJson,
            $this->normaliseJson(
                <<<'JSON'
                {
                    "repositories": {
                        "https://github.com/php/pie": {
                            "type": "vcs",
                            "url": "https://github.com/php/pie"
                        }
                    }
                }
                JSON,
            ),
        );

        $editor->addRepository('vcs', 'https://github.com/asgrim/example_pie_extension');

        self::assertSame(
            $this->normaliseJson(
                <<<'JSON'
                {
                    "repositories": [
                        {
                            "name": "https://github.com/php/pie",
                            "type": "vcs",
                            "url": "https://github.com/php/pie"
                        },
                        {
                            "name": "https://github.com/asgrim/example_pie_extension",
                            "type": "vcs",
                            "url": "https://github.com/asgrim/example_pie_extension"
                        }
                    ]
                }
                JSON,
            ),
            $this->normaliseJson(file_get_contents($testPieJson)),
        );
    }

    public function testCanAddRequire(): void
    {
        $testPieJson = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_json_test_', true) . '.json';

        $editor = new PieJsonEditor($testPieJson, dirname($testPieJson));
        $editor->ensureExists();

        $editor->addRequire('foo/bar', '^1.2');
        self::assertSame(
            $this->normaliseJson(<<<'EOF'
            {
                "require": {
                    "foo/bar": "^1.2"
                }
            }
            EOF),
            $this->normaliseJson(file_get_contents($testPieJson)),
        );

        $editor->removeRequire('foo/bar');
        self::assertSame(
            $this->normaliseJson('{}'),
            $this->normaliseJson(file_get_contents($testPieJson)),
        );
    }

    public function testCanRevert(): void
    {
        $testPieJson = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_json_test_', true) . '.json';

        $editor = new PieJsonEditor($testPieJson, dirname($testPieJson));
        $editor->ensureExists();
        $originalContent = $editor->addRequire('foo/bar', '^1.2');
        $editor->revert($originalContent);
        self::assertSame(
            $this->normaliseJson($originalContent),
            $this->normaliseJson(file_get_contents($testPieJson)),
        );
    }

    public function testCanAddAndRemoveRepositories(): void
    {
        $testPieJson = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_json_test_', true) . '.json';

        $editor = new PieJsonEditor($testPieJson, dirname($testPieJson));
        $editor->ensureExists();

        $originalContent = $editor->addRepository(
            'vcs',
            'https://github.com/php/pie',
        );

        self::assertSame(
            $this->normaliseJson("{\n}\n"),
            $this->normaliseJson($originalContent),
        );

        $expectedRepoContent = $this->normaliseJson(<<<'EOF'
            {
                "repositories": [
                    {
                        "name": "https://github.com/php/pie",
                        "type": "vcs",
                        "url": "https://github.com/php/pie"
                    }
                ]
            }
            EOF);

        self::assertSame(
            $expectedRepoContent,
            $this->normaliseJson(file_get_contents($testPieJson)),
        );

        $originalContent2 = $editor->removeRepository('https://github.com/php/pie');
        self::assertSame(
            $expectedRepoContent,
            $this->normaliseJson($originalContent2),
        );

        $noRepositoriesContent = $this->normaliseJson(<<<'EOF'
            {
            }
            EOF);

        self::assertSame(
            $noRepositoriesContent,
            $this->normaliseJson(file_get_contents($testPieJson)),
        );

        $originalContent3 = $editor->excludePackagistOrg();
        self::assertSame(
            $noRepositoriesContent,
            $this->normaliseJson($originalContent3),
        );

        self::assertSame(
            $this->normaliseJson(<<<'EOF'
            {
                "repositories": [
                    {
                        "packagist.org": false
                    }
                ]
            }
            EOF),
            $this->normaliseJson(file_get_contents($testPieJson)),
        );
    }

    public function testCanAddAndRemoveWithTrailingSlash(): void
    {
        $testPieJson = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_json_test_', true) . '.json';

        $editor = new PieJsonEditor($testPieJson, dirname($testPieJson));
        $editor->ensureExists();

        $originalContent = $editor->addRepository(
            'path',
            '/pwd/dummy/',
        );

        self::assertSame(
            $this->normaliseJson("{\n}\n"),
            $this->normaliseJson($originalContent),
        );

        $expectedRepoContent = $this->normaliseJson(<<<'EOF'
            {
                "repositories": [
                    {
                        "name": "/pwd/dummy",
                        "type": "path",
                        "url": "/pwd/dummy/"
                    }
                ]
            }
            EOF);

        self::assertSame(
            $expectedRepoContent,
            $this->normaliseJson(file_get_contents($testPieJson)),
        );

        $originalContent2 = $editor->removeRepository('/pwd/dummy/');
        self::assertSame(
            $expectedRepoContent,
            $this->normaliseJson($originalContent2),
        );

        $noRepositoriesContent = $this->normaliseJson(<<<'EOF'
            {
            }
            EOF);

        self::assertSame(
            $noRepositoriesContent,
            $this->normaliseJson(file_get_contents($testPieJson)),
        );
    }

    private function normaliseJson(string|false $fileContent): string
    {
        return (string) json_encode(json_decode((string) $fileContent));
    }
}
