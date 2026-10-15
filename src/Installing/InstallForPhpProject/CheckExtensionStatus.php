<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\Version\VersionParser;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionVersion;
use Php\Pie\Platform\PiePackageList;
use Php\Pie\Util\Emoji;

use function array_key_exists;
use function count;
use function sprintf;
use function strtolower;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class CheckExtensionStatus
{
    public function __construct(private readonly IOInterface $io)
    {
    }

    /** @param array<string, string> $phpEnabledExtensions */
    public function __invoke(
        Link $link,
        PiePackageList $piePackagesForExtension,
        array $phpEnabledExtensions,
    ): ExtensionInstalledStatus {
        $extension               = ExtensionName::normaliseFromString($link->getTarget());
        $lowercasedExtensionName = strtolower($extension->name());
        $linkRequiresConstraint  = $link->getPrettyConstraint();

        $installedPieOrExtensionVersion = $phpEnabledExtensions[$lowercasedExtensionName] ?? null;

        if (count($piePackagesForExtension) === 1) {
            $installedPieOrExtensionVersion = $piePackagesForExtension->onlyOne()->version();
        }

        $piePackageVersionMatchesLinkConstraint = null;
        if ($installedPieOrExtensionVersion !== null) {
            $piePackageVersionMatchesLinkConstraint = $link
                ->getConstraint()
                ->matches(
                    (new VersionParser())->parseConstraints(ExtensionVersion::parse($installedPieOrExtensionVersion)->normalised),
                );
        }

        if (array_key_exists($lowercasedExtensionName, $phpEnabledExtensions)) {
            if ($installedPieOrExtensionVersion !== null && $piePackageVersionMatchesLinkConstraint === false) {
                $this->io->write(sprintf(
                    '%s: <comment>%s:%s</comment> %s Version %s is installed, but does not meet the version requirement',
                    $link->getDescription(),
                    $extension->nameWithExtPrefix(),
                    $linkRequiresConstraint,
                    Emoji::WARNING,
                    $installedPieOrExtensionVersion,
                ));

                return ExtensionInstalledStatus::ConstraintNotMet;
            }

            $this->io->write(sprintf(
                '%s: <info>%s:%s</info> %s Already installed',
                $link->getDescription(),
                $extension->nameWithExtPrefix(),
                $linkRequiresConstraint,
                Emoji::GREEN_CHECKMARK,
            ));

            return ExtensionInstalledStatus::Installed;
        }

        $this->io->write(sprintf(
            '%s: <comment>%s:%s</comment> %s Missing',
            $link->getDescription(),
            $extension->nameWithExtPrefix(),
            $linkRequiresConstraint,
            Emoji::PROHIBITED,
        ));

        return ExtensionInstalledStatus::Missing;
    }
}
