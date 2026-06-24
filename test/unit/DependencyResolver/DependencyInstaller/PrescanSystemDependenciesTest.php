<?php

declare(strict_types=1);

namespace Php\PieUnitTest\DependencyResolver\DependencyInstaller;

use Composer\Composer;
use Composer\IO\BufferIO;
use Composer\Package\CompletePackage;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\VersionParser;
use Php\Pie\DependencyResolver\DependencyInstaller\PrescanSystemDependencies;
use Php\Pie\DependencyResolver\DependencyInstaller\SystemDependenciesDefinition;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\DependencyResolver\DependencyStatus;
use Php\Pie\DependencyResolver\FetchDependencyStatuses;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\DependencyResolver\ResolvedPackageRequest;
use Php\Pie\Platform\PackageManager;
use Php\Pie\Platform\TargetPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\StreamOutput;

#[CoversClass(PrescanSystemDependencies::class)]
final class PrescanSystemDependenciesTest extends TestCase
{
    private readonly DependencyResolver&MockObject $dependencyResolver;
    private readonly FetchDependencyStatuses&MockObject $fetchDependencyStatuses;
    private readonly BufferIO $io;
    private readonly Composer&MockObject $composer;
    private readonly TargetPlatform&MockObject $targetPlatform;

    public function setUp(): void
    {
        parent::setUp();

        $this->dependencyResolver      = $this->createMock(DependencyResolver::class);
        $this->fetchDependencyStatuses = $this->createMock(FetchDependencyStatuses::class);
        $this->io                      = new BufferIO(verbosity: StreamOutput::VERBOSITY_VERBOSE);
        $this->composer                = $this->createMock(Composer::class);
        $this->targetPlatform          = $this->createMock(TargetPlatform::class);
    }

    public function testNoPackageManager(): void
    {
        $scanner = new PrescanSystemDependencies(
            $this->dependencyResolver,
            $this->fetchDependencyStatuses,
            new SystemDependenciesDefinition([]),
            null,
            $this->io,
        );

        ($scanner)($this->composer, $this->targetPlatform, new RequestedPackageAndVersion('foo/foo', null), true);

        self::assertStringContainsString(
            'Skipping pre-scan of system dependencies, as a supported package manager could not be detected.',
            $this->io->getOutput(),
        );
    }

    public function testAllDependenciesSatisfied(): void
    {
        $scanner = new PrescanSystemDependencies(
            $this->dependencyResolver,
            $this->fetchDependencyStatuses,
            new SystemDependenciesDefinition([]),
            PackageManager::Test,
            $this->io,
        );

        $request         = new RequestedPackageAndVersion('foo/foo', null);
        $composerPackage = new CompletePackage('foo/foo', '1.0.0.0', '1.0.0');
        $piePackage      = Package::fromComposerCompletePackage($composerPackage);
        $this->dependencyResolver->expects(self::once())
            ->method('__invoke')
            ->with($this->composer, $this->targetPlatform, $request, true)
            ->willReturn(new ResolvedPackageRequest($piePackage, $request));

        $versionParser = new VersionParser();

        $this->fetchDependencyStatuses->expects(self::once())
            ->method('__invoke')
            ->with($this->targetPlatform, $this->composer, $composerPackage)
            ->willReturn([
                new DependencyStatus('lib-foo', $versionParser->parseConstraints('^1.0'), new Constraint('=', '1.0.0.0')),
                new DependencyStatus('lib-bar', $versionParser->parseConstraints('^2.0'), new Constraint('=', '2.5.1.0')),
            ]);

        ($scanner)($this->composer, $this->targetPlatform, $request, true);

        self::assertStringContainsString(
            'All system dependencies are already installed.',
            $this->io->getOutput(),
        );
    }

    public function testMissingDependencyThatDoesNotHaveAnyPackageManagerDefinition(): void
    {
        $scanner = new PrescanSystemDependencies(
            $this->dependencyResolver,
            $this->fetchDependencyStatuses,
            new SystemDependenciesDefinition([]),
            PackageManager::Test,
            $this->io,
        );

        $request         = new RequestedPackageAndVersion('foo/foo', null);
        $composerPackage = new CompletePackage('foo/foo', '1.0.0.0', '1.0.0');
        $piePackage      = Package::fromComposerCompletePackage($composerPackage);
        $this->dependencyResolver->expects(self::once())
            ->method('__invoke')
            ->with($this->composer, $this->targetPlatform, $request, true)
            ->willReturn(new ResolvedPackageRequest($piePackage, $request));

        $versionParser = new VersionParser();

        $this->fetchDependencyStatuses->expects(self::once())
            ->method('__invoke')
            ->with($this->targetPlatform, $this->composer, $composerPackage)
            ->willReturn([
                new DependencyStatus('lib-bar', $versionParser->parseConstraints('^1.0'), null),
            ]);

        ($scanner)($this->composer, $this->targetPlatform, $request, true);

        $outputString = $this->io->getOutput();
        self::assertStringContainsString('Extension foo/foo has unmet dependencies: lib-bar', $outputString);
        self::assertStringContainsString('Could not automatically install "lib-bar", as PIE does not have the package manager definition.', $outputString);
        self::assertStringContainsString('No system dependencies could be installed automatically by PIE.', $outputString);
    }

    public function testMissingDependencyThatDoesNotHaveMyPackageManagerDefinition(): void
    {
        $scanner = new PrescanSystemDependencies(
            $this->dependencyResolver,
            $this->fetchDependencyStatuses,
            new SystemDependenciesDefinition([
                'bar' => [
                    PackageManager::Apt->value => 'libbar-dev',
                    PackageManager::Apk->value => 'libbar-dev',
                ],
            ]),
            PackageManager::Test,
            $this->io,
        );

        $request         = new RequestedPackageAndVersion('foo/foo', null);
        $composerPackage = new CompletePackage('foo/foo', '1.0.0.0', '1.0.0');
        $piePackage      = Package::fromComposerCompletePackage($composerPackage);
        $this->dependencyResolver->expects(self::once())
            ->method('__invoke')
            ->with($this->composer, $this->targetPlatform, $request, true)
            ->willReturn(new ResolvedPackageRequest($piePackage, $request));

        $versionParser = new VersionParser();

        $this->fetchDependencyStatuses->expects(self::once())
            ->method('__invoke')
            ->with($this->targetPlatform, $this->composer, $composerPackage)
            ->willReturn([
                new DependencyStatus('lib-bar', $versionParser->parseConstraints('^1.0'), null),
            ]);

        ($scanner)($this->composer, $this->targetPlatform, $request, true);

        $outputString = $this->io->getOutput();
        self::assertStringContainsString('Extension foo/foo has unmet dependencies: lib-bar', $outputString);
        self::assertStringContainsString('Could not automatically install "lib-bar", as PIE does not have a definition for "test"', $outputString);
        self::assertStringContainsString('No system dependencies could be installed automatically by PIE.', $outputString);
    }

    public function testMissingDependenciesFailToInstall(): void
    {
        $scanner = new PrescanSystemDependencies(
            $this->dependencyResolver,
            $this->fetchDependencyStatuses,
            new SystemDependenciesDefinition([
                'bar' => [
                    PackageManager::Apk->value => 'hopefully-this-package-does-not-exist-in-apk',
                    PackageManager::Test->value => 'libbar-dev',
                ],
            ]),
            PackageManager::Apk,
            $this->io,
        );

        $request         = new RequestedPackageAndVersion('foo/foo', null);
        $composerPackage = new CompletePackage('foo/foo', '1.0.0.0', '1.0.0');
        $piePackage      = Package::fromComposerCompletePackage($composerPackage);
        $this->dependencyResolver->expects(self::once())
            ->method('__invoke')
            ->with($this->composer, $this->targetPlatform, $request, true)
            ->willReturn(new ResolvedPackageRequest($piePackage, $request));

        $versionParser = new VersionParser();

        $this->fetchDependencyStatuses->expects(self::once())
            ->method('__invoke')
            ->with($this->targetPlatform, $this->composer, $composerPackage)
            ->willReturn([
                new DependencyStatus('lib-bar', $versionParser->parseConstraints('^1.0'), null),
            ]);

        ($scanner)($this->composer, $this->targetPlatform, $request, true);

        $outputString = $this->io->getOutput();
        self::assertStringContainsString('Extension foo/foo has unmet dependencies: lib-bar', $outputString);
        self::assertStringContainsString('Failed to install missing system dependencies', $outputString);
    }

    public function testMissingDependenciesAreSuccessfullyInstalled(): void
    {
        $scanner = new PrescanSystemDependencies(
            $this->dependencyResolver,
            $this->fetchDependencyStatuses,
            new SystemDependenciesDefinition([
                'bar' => [
                    PackageManager::Apt->value => 'libbar-dev',
                    PackageManager::Apk->value => 'libbar-dev',
                    PackageManager::Test->value => 'libbar-dev',
                ],
            ]),
            PackageManager::Test,
            $this->io,
        );

        $request         = new RequestedPackageAndVersion('foo/foo', null);
        $composerPackage = new CompletePackage('foo/foo', '1.0.0.0', '1.0.0');
        $piePackage      = Package::fromComposerCompletePackage($composerPackage);
        $this->dependencyResolver->expects(self::once())
            ->method('__invoke')
            ->with($this->composer, $this->targetPlatform, $request, true)
            ->willReturn(new ResolvedPackageRequest($piePackage, $request));

        $versionParser = new VersionParser();

        $this->fetchDependencyStatuses->expects(self::once())
            ->method('__invoke')
            ->with($this->targetPlatform, $this->composer, $composerPackage)
            ->willReturn([
                new DependencyStatus('lib-bar', $versionParser->parseConstraints('^1.0'), null),
            ]);

        ($scanner)($this->composer, $this->targetPlatform, $request, true);

        $outputString = $this->io->getOutput();
        self::assertStringContainsString('Extension foo/foo has unmet dependencies: lib-bar', $outputString);
        self::assertStringContainsString('Adding test package libbar-dev to be installed for lib-bar', $outputString);
        self::assertStringContainsString('Need to install missing system dependencies: echo "fake installing libbar-dev"', $outputString);
    }

    public function testMissingDependenciesAreNotInstalledWhenShouldNotAutoInstallAndNonInteractive(): void
    {
        $scanner = new PrescanSystemDependencies(
            $this->dependencyResolver,
            $this->fetchDependencyStatuses,
            new SystemDependenciesDefinition([
                'bar' => [
                    PackageManager::Apt->value => 'libbar-dev',
                    PackageManager::Apk->value => 'libbar-dev',
                    PackageManager::Test->value => 'libbar-dev',
                ],
            ]),
            PackageManager::Test,
            $this->io,
        );

        $request         = new RequestedPackageAndVersion('foo/foo', null);
        $composerPackage = new CompletePackage('foo/foo', '1.0.0.0', '1.0.0');
        $piePackage      = Package::fromComposerCompletePackage($composerPackage);
        $this->dependencyResolver->expects(self::once())
            ->method('__invoke')
            ->with($this->composer, $this->targetPlatform, $request, true)
            ->willReturn(new ResolvedPackageRequest($piePackage, $request));

        $versionParser = new VersionParser();

        $this->fetchDependencyStatuses->expects(self::once())
            ->method('__invoke')
            ->with($this->targetPlatform, $this->composer, $composerPackage)
            ->willReturn([
                new DependencyStatus('lib-bar', $versionParser->parseConstraints('^1.0'), null),
            ]);

        ($scanner)($this->composer, $this->targetPlatform, $request, false);

        $outputString = $this->io->getOutput();
        self::assertStringContainsString('Extension foo/foo has unmet dependencies: lib-bar', $outputString);
        self::assertStringContainsString('Adding test package libbar-dev to be installed for lib-bar', $outputString);
        self::assertStringContainsString('You are not running in interactive mode, and you did not provide the --auto-install-system-dependencies flag.', $outputString);
        self::assertStringContainsString('You may need to run: echo "fake installing libbar-dev"', $outputString);
        self::assertStringNotContainsString('Need to install missing system dependencies', $outputString);
    }
}
