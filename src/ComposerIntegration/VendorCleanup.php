<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Safe\Exceptions\DirException;
use Webmozart\Assert\Assert;

use function array_filter;
use function array_walk;
use function in_array;
use function Safe\scandir;
use function sprintf;

use const DIRECTORY_SEPARATOR;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class VendorCleanup
{
    public function __construct(
        private readonly IOInterface $io,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function __invoke(Composer $composer): void
    {
        $vendorDir = (string) $composer->getConfig()->get('vendor-dir');

        try {
            $vendorContents = scandir($vendorDir);
            Assert::isList($vendorContents);
            Assert::allString($vendorContents);
        } catch (DirException $e) {
            $this->io->write(
                sprintf(
                    '<comment>Vendor directory (vendor-dir config) %s seemed invalid? %s</comment>',
                    $vendorDir,
                    $e->getMessage(),
                ),
                verbosity: IOInterface::VERY_VERBOSE,
            );

            return;
        }

        $toRemove = array_filter(
            $vendorContents,
            static function (string $path): bool {
                return ! in_array(
                    $path,
                    [
                        '.',
                        '..',
                        'autoload.php',
                        'composer',
                    ],
                );
            },
        );

        array_walk(
            $toRemove,
            function (string $pathToRemove) use ($vendorDir): void {
                $fullPathToRemove = $vendorDir . DIRECTORY_SEPARATOR . $pathToRemove;

                $this->io->write(
                    sprintf(
                        '<comment>Removing: %s</comment>',
                        $fullPathToRemove,
                    ),
                    verbosity: IOInterface::VERY_VERBOSE,
                );

                if ($this->filesystem->remove($fullPathToRemove)) {
                    return;
                }

                $this->io->write(
                    sprintf(
                        '<comment>Warning: failed to remove %s</comment>',
                        $fullPathToRemove,
                    ),
                    verbosity: IOInterface::VERBOSE,
                );
            },
        );
    }
}
