<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\Version\VersionParser;
use Php\Pie\ExtensionName;
use Php\Pie\Platform\PiePackageList;
use Php\Pie\Util\Emoji;

use function count;
use function in_array;
use function sprintf;
use function strtolower;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class CheckExtensionStatus
{
    public function __construct(private readonly IOInterface $io)
    {
    }

    /**
     * Check if an extension is installed; returns true if it's installed, but outputs a warning if the version
     * constraint doesn't match. Returns true of the extension is missing.
     *
     * @param list<string> $phpEnabledExtensions
     */
    public function __invoke(
        Link $link,
        PiePackageList $piePackagesForExtension,
        array $phpEnabledExtensions,
    ): bool {
        $extension              = ExtensionName::normaliseFromString($link->getTarget());
        $linkRequiresConstraint = $link->getPrettyConstraint();

        $piePackageVersion = null;

        if (count($piePackagesForExtension) === 1) {
            $piePackageVersion = $piePackagesForExtension->onlyOne()->version();
        }

        $piePackageVersionMatchesLinkConstraint = null;
        if ($piePackageVersion !== null) {
            $piePackageVersionMatchesLinkConstraint = $link
                ->getConstraint()
                ->matches(
                    (new VersionParser())->parseConstraints($piePackageVersion),
                );
        }

        if (in_array(strtolower($extension->name()), $phpEnabledExtensions)) {
            if ($piePackageVersion !== null && $piePackageVersionMatchesLinkConstraint === false) {
                $this->io->write(sprintf(
                    '%s: <comment>%s:%s</comment> %s Version %s is installed, but does not meet the version requirement',
                    $link->getDescription(),
                    $extension->nameWithExtPrefix(),
                    $linkRequiresConstraint,
                    Emoji::WARNING,
                    $piePackageVersion,
                ));

                return true;
            }

            $this->io->write(sprintf(
                '%s: <info>%s:%s</info> %s Already installed',
                $link->getDescription(),
                $extension->nameWithExtPrefix(),
                $linkRequiresConstraint,
                Emoji::GREEN_CHECKMARK,
            ));

            return true;
        }

        $this->io->write(sprintf(
            '%s: <comment>%s:%s</comment> %s Missing',
            $link->getDescription(),
            $extension->nameWithExtPrefix(),
            $linkRequiresConstraint,
            Emoji::PROHIBITED,
        ));

        return false;
    }
}
