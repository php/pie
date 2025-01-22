<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Config\JsonConfigSource;
use Composer\Json\JsonFile;

use function file_exists;
use function file_get_contents;
use function file_put_contents;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class PieJsonEditor
{
    public function __construct(private readonly string $pieJsonFilename)
    {
    }

    /**
     * Ensure the given `pie.json` exists; if it does not exist, create an
     * empty but valid `pie.json`.
     */
    public function ensureExists(): void
    {
        if (file_exists($this->pieJsonFilename)) {
            return;
        }

        file_put_contents(
            $this->pieJsonFilename,
            "{\n}\n",
        );
    }

    /**
     * Add a package to the `require` section of the given `pie.json`. Returns
     * the original `pie.json` content, in case it needs to be restored later.
     *
     * @param non-empty-string $package
     * @param non-empty-string $version
     */
    public function addRequire(string $package, string $version): string
    {
        $originalPieJsonContent = file_get_contents($this->pieJsonFilename);

        (new JsonConfigSource(
            new JsonFile(
                $this->pieJsonFilename,
            ),
        ))->addLink('require', $package, $version);

        return $originalPieJsonContent;
    }

    public function revert(string $originalPieJsonContent): void
    {
        file_put_contents($this->pieJsonFilename, $originalPieJsonContent);
    }

    /**
     * Add a repository to the given `pie.json`. Returns the original
     * `pie.json` content, in case it needs to be restored later.
     *
     * @param 'vcs'|'path'|'composer' $type
     * @param non-empty-string        $url
     */
    public function addRepository(
        string $type,
        string $url,
    ): string {
        $originalPieJsonContent = file_get_contents($this->pieJsonFilename);

        (new JsonConfigSource(
            new JsonFile(
                $this->pieJsonFilename,
            ),
        ))
            ->addRepository($url, [
                'type' => $type,
                'url' => $url,
            ]);

        return $originalPieJsonContent;
    }

    /**
     * Remove a repository from the given `pie.json`. Returns the original
     * `pie.json` content, in case it needs to be restored later.
     *
     * @param non-empty-string $name
     */
    public function removeRepository(
        string $name,
    ): string {
        $originalPieJsonContent = file_get_contents($this->pieJsonFilename);

        (new JsonConfigSource(
            new JsonFile(
                $this->pieJsonFilename,
            ),
        ))
            ->removeRepository($name);

        return $originalPieJsonContent;
    }
}
