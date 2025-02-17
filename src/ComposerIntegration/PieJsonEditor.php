<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Config\JsonConfigSource;
use Composer\Json\JsonFile;
use Php\Pie\Platform;
use Php\Pie\Platform\TargetPlatform;
use RuntimeException;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function rtrim;
use function sprintf;
use function str_replace;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class PieJsonEditor
{
    public const PACKAGIST_ORG_KEY = 'packagist.org';

    public function __construct(
        private readonly string $pieJsonFilename,
        private readonly string $pieWorkingDirectory,
    ) {
    }

    public static function fromTargetPlatform(TargetPlatform $targetPlatform): self
    {
        return new self(
            Platform::getPieJsonFilename($targetPlatform),
            Platform::getPieWorkingDirectory($targetPlatform),
        );
    }

    /**
     * Ensure the given `pie.json` exists; if it does not exist, create an
     * empty but valid `pie.json`.
     */
    public function ensureExists(): self
    {
        if (file_exists($this->pieJsonFilename)) {
            return $this;
        }

        if (! file_exists($this->pieWorkingDirectory)) {
            mkdir($this->pieWorkingDirectory, recursive: true);
        }

        if (file_put_contents($this->pieJsonFilename, "{\n}\n") === false) {
            throw new RuntimeException(sprintf(
                'Failed to create pie.json in %s (working directory: %s)',
                $this->pieJsonFilename,
                $this->pieWorkingDirectory,
            ));
        }

        return $this;
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

    /**
     * Remove a package from the `require` section of the given `pie.json`.
     * Returns the original `pie.json` content, in case it needs to be
     * restored later.
     *
     * @param non-empty-string $package
     */
    public function removeRequire(string $package): string
    {
        $originalPieJsonContent = file_get_contents($this->pieJsonFilename);

        (new JsonConfigSource(
            new JsonFile(
                $this->pieJsonFilename,
            ),
        ))->removeLink('require', $package);

        return $originalPieJsonContent;
    }

    public function revert(string $originalPieJsonContent): void
    {
        file_put_contents($this->pieJsonFilename, $originalPieJsonContent);
    }

    public function excludePackagistOrg(): string
    {
        $originalPieJsonContent = file_get_contents($this->pieJsonFilename);

        (new JsonConfigSource(
            new JsonFile(
                $this->pieJsonFilename,
            ),
        ))
            ->addRepository(self::PACKAGIST_ORG_KEY, false);

        return $originalPieJsonContent;
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
            ->addRepository($this->normaliseRepositoryName($url), [
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
            ->removeRepository($this->normaliseRepositoryName($name));

        return $originalPieJsonContent;
    }

    private function normaliseRepositoryName(string $url): string
    {
        return rtrim(str_replace('\\', '/', $url), '/');
    }
}
