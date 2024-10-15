<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Composer;
use Composer\Installer;
use Composer\IO\IOInterface;
use Composer\Json\JsonManipulator;
use Composer\Package\CompletePackageInterface;
use Composer\Package\Link;
use Composer\Package\Locker;
use Composer\Package\Version\VersionSelector;
use Composer\Repository\CompositeRepository;
use Composer\Repository\RepositorySet;
use Composer\Semver\VersionParser;
use Illuminate\Container\Container;
use Php\Pie\ExtensionType;
use Php\Pie\Platform;
use Php\Pie\Platform\TargetPhp\ResolveTargetPhpToPlatformRepository;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;

use function preg_match;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class ResolveDependencyWithComposer implements DependencyResolver
{
    public function __construct(
        private Container $container,
        private Composer $composer,
        private readonly ResolveTargetPhpToPlatformRepository $resolveTargetPhpToPlatformRepository,
        private readonly IOInterface $io,
    ) {
    }

    private function factoryRepositorySet(string|null $requestedVersion): RepositorySet
    {
        $repositorySet = new RepositorySet(DetermineMinimumStability::fromRequestedVersion($requestedVersion));
        $repositorySet->addRepository(new CompositeRepository($this->composer->getRepositoryManager()->getRepositories()));

        return $repositorySet;
    }

    public function __invoke(TargetPlatform $targetPlatform, string $packageName, string|null $requestedVersion): Package
    {
        $io = new ArrayCollectionIO();


        $versionSelector = (new VersionSelector(
            $this->factoryRepositorySet($requestedVersion),
            ($this->resolveTargetPhpToPlatformRepository)($targetPlatform->phpBinaryPath),
        ));
        $package = $versionSelector->findBestCandidate($packageName, $requestedVersion);
        $recommendedRequireVersion = $versionSelector->findRecommendedRequireVersion($package);
        $constraint = (new VersionParser())->parseConstraints($recommendedRequireVersion);

        $pieComposerJson = Platform::getPieComposerJsonFilename();
        $manipulator = new JsonManipulator(file_get_contents($pieComposerJson));
        $manipulator->addLink('require', $packageName, $recommendedRequireVersion, true);
        file_put_contents($pieComposerJson, $manipulator->getContents());
//        $rootPackage = $this->composer->getPackage();
//        $rootPackage->setRequires([
//            $packageName => new Link($packageName, $packageName, $constraint),
//        ]);

        $this->container->forgetInstance(Composer::class);
        $this->composer = $this->container->make(Composer::class);

        $i = Installer::create($this->io, $this->composer);
        $i
            ->setAllowedTypes(['php-ext', 'php-ext-zend'])
            ->setUpdate(true)
            ->setIgnoredTypes([])
            ->setDryRun(false)
            ->setDownloadOnly(false)
            ->setVerbose(true)
        ;
        $i->run();

        die("FIN\n");

        $package = (new VersionSelector(
            $this->factoryRepositorySet($requestedVersion),
            ($this->resolveTargetPhpToPlatformRepository)($targetPlatform->phpBinaryPath),
        ))
            ->findBestCandidate($packageName, $requestedVersion, io: $io);

        if (! $package instanceof CompletePackageInterface) {
            throw UnableToResolveRequirement::fromRequirement($packageName, $requestedVersion, $io);
        }

        /**
         * If a specific commit hash is requested, override the references in the package. This is approximately what
         * Composer does anyway:
         *
         * > ArrayLoader::parseLinks is in charge of this, it drops commit refs, for package resolution purposes we
         * > only use dev-main, but we ensure in the PoolBuilder that root references (#...) are set so the dev-main
         * > package has its source and dist refs overridden to be whatever you specify and that applies at install
         * > time then but package metadata is only read from the branch's head
         */
        if ($requestedVersion !== null && preg_match('/#([a-f0-9]{40})$/', $requestedVersion, $matches)) {
            $package->setSourceDistReferences($matches[1]);
        }

        if (! ExtensionType::isValid($package->getType())) {
            throw UnableToResolveRequirement::toPhpOrZendExtension($package, $packageName, $requestedVersion);
        }

        $package = Package::fromComposerCompletePackage($package);

        $this->assertCompatibleThreadSafetyMode($targetPlatform->threadSafety, $package);

        return $package;
    }

    private function assertCompatibleThreadSafetyMode(ThreadSafetyMode $threadSafetyMode, Package $resolvedPackage): void
    {
        if ($threadSafetyMode === ThreadSafetyMode::NonThreadSafe && ! $resolvedPackage->supportNts) {
            throw IncompatibleThreadSafetyMode::ztsExtensionOnNtsPlatform();
        }

        if ($threadSafetyMode === ThreadSafetyMode::ThreadSafe && ! $resolvedPackage->supportZts) {
            throw IncompatibleThreadSafetyMode::ntsExtensionOnZtsPlatform();
        }
    }
}
