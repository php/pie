<?php

declare(strict_types=1);

namespace Php\PieUnitTest\DependencyResolver\DependencyInstaller;

use Composer\Composer;
use Composer\IO\BufferIO;
use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryManager;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\VersionParser;
use Php\Pie\ComposerIntegration\PhpBinaryPathBasedPlatformRepository;
use Php\Pie\DependencyResolver\DependencyInstaller\PrescanSystemDependencies;
use Php\Pie\DependencyResolver\DependencyInstaller\SystemDependenciesDefinition;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\DependencyResolver\DependencyStatus;
use Php\Pie\DependencyResolver\FetchDependencyStatuses;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\DependencyResolver\ResolvedPackageRequest;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\PackageManager;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\StreamOutput;

use function implode;

#[CoversClass(PrescanSystemDependencies::class)]
final class PrescanSystemDependenciesTest extends TestCase
{
    private readonly DependencyResolver&MockObject $dependencyResolver;
    private readonly FetchDependencyStatuses&MockObject $fetchDependencyStatuses;
    private readonly BufferIO $io;
    private readonly Composer&MockObject $composer;
    private readonly TargetPlatform&MockObject $targetPlatform;
    private readonly PlatformRepository&MockObject $platformRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->dependencyResolver      = $this->createMock(DependencyResolver::class);
        $this->fetchDependencyStatuses = $this->createMock(FetchDependencyStatuses::class);
        $this->io                      = new BufferIO(verbosity: StreamOutput::VERBOSITY_VERBOSE);
        $this->composer                = $this->createMock(Composer::class);
        $this->targetPlatform          = $this->createMock(TargetPlatform::class);
        $this->platformRepository      = $this->createMock(PlatformRepository::class);
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

        $systemDependenciesWereInstalled = ($scanner)($this->composer, $this->targetPlatform, $this->platformRepository, new RequestedPackageAndVersion('foo/foo', null), true);

        self::assertFalse($systemDependenciesWereInstalled);

        self::assertStringContainsString(
            'Skipping pre-scan of system dependencies, as a supported package manager could not be detected.',
            $this->io->getOutput(),
        );
    }

    public function testTargetPhpIsAskedForItsExtensionsOnceWhenAllDependenciesAreSatisfied(): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->method('version')->willReturn('8.3.0');
        $phpBinaryPath->expects(self::once())
            ->method('extensions')
            ->willReturn(['Core' => '8.3.0']);

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::NonThreadSafe,
            1,
            null,
            null,
        );

        $localRepository = $this->createMock(InstalledRepositoryInterface::class);
        $localRepository->method('getPackages')->willReturn([]);
        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager->method('getLocalRepository')->willReturn($localRepository);
        $this->composer->method('getRepositoryManager')->willReturn($repositoryManager);

        $request         = new RequestedPackageAndVersion('foo/foo', null);
        $composerPackage = new CompletePackage('foo/foo', '1.0.0.0', '1.0.0');
        $composerPackage->setRequires([
            'php' => new Link('foo/foo', 'php', (new VersionParser())->parseConstraints('^8.0')),
        ]);
        $this->dependencyResolver->method('__invoke')
            ->willReturn(new ResolvedPackageRequest(Package::fromComposerCompletePackage($composerPackage), $request));

        $scanner = new PrescanSystemDependencies(
            $this->dependencyResolver,
            new FetchDependencyStatuses(),
            new SystemDependenciesDefinition([]),
            PackageManager::Test,
            $this->io,
        );

        ($scanner)($this->composer, $targetPlatform, PhpBinaryPathBasedPlatformRepository::forTargetPlatform($targetPlatform, $this->composer), $request, true);

        self::assertStringContainsString(
            'All system dependencies are already installed.',
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
            ->with($this->composer, $this->targetPlatform, $this->platformRepository, $request, true)
            ->willReturn(new ResolvedPackageRequest($piePackage, $request));

        $versionParser = new VersionParser();

        $this->fetchDependencyStatuses->expects(self::once())
            ->method('__invoke')
            ->with($this->platformRepository, $composerPackage)
            ->willReturn([
                new DependencyStatus('lib-foo', $versionParser->parseConstraints('^1.0'), new Constraint('=', '1.0.0.0')),
                new DependencyStatus('lib-bar', $versionParser->parseConstraints('^2.0'), new Constraint('=', '2.5.1.0')),
            ]);

        $systemDependenciesWereInstalled = ($scanner)($this->composer, $this->targetPlatform, $this->platformRepository, $request, true);

        self::assertFalse($systemDependenciesWereInstalled);

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
            ->with($this->composer, $this->targetPlatform, $this->platformRepository, $request, true)
            ->willReturn(new ResolvedPackageRequest($piePackage, $request));

        $versionParser = new VersionParser();

        $this->fetchDependencyStatuses->expects(self::once())
            ->method('__invoke')
            ->with($this->platformRepository, $composerPackage)
            ->willReturn([
                new DependencyStatus('lib-bar', $versionParser->parseConstraints('^1.0'), null),
            ]);

        $systemDependenciesWereInstalled = ($scanner)($this->composer, $this->targetPlatform, $this->platformRepository, $request, true);

        self::assertFalse($systemDependenciesWereInstalled);

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
            ->with($this->composer, $this->targetPlatform, $this->platformRepository, $request, true)
            ->willReturn(new ResolvedPackageRequest($piePackage, $request));

        $versionParser = new VersionParser();

        $this->fetchDependencyStatuses->expects(self::once())
            ->method('__invoke')
            ->with($this->platformRepository, $composerPackage)
            ->willReturn([
                new DependencyStatus('lib-bar', $versionParser->parseConstraints('^1.0'), null),
            ]);

        $systemDependenciesWereInstalled = ($scanner)($this->composer, $this->targetPlatform, $this->platformRepository, $request, true);

        self::assertFalse($systemDependenciesWereInstalled);

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
            ->with($this->composer, $this->targetPlatform, $this->platformRepository, $request, true)
            ->willReturn(new ResolvedPackageRequest($piePackage, $request));

        $versionParser = new VersionParser();

        $this->fetchDependencyStatuses->expects(self::once())
            ->method('__invoke')
            ->with($this->platformRepository, $composerPackage)
            ->willReturn([
                new DependencyStatus('lib-bar', $versionParser->parseConstraints('^1.0'), null),
            ]);

        $systemDependenciesWereInstalled = ($scanner)($this->composer, $this->targetPlatform, $this->platformRepository, $request, true);

        self::assertTrue($systemDependenciesWereInstalled);

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
            ->with($this->composer, $this->targetPlatform, $this->platformRepository, $request, true)
            ->willReturn(new ResolvedPackageRequest($piePackage, $request));

        $versionParser = new VersionParser();

        $this->fetchDependencyStatuses->expects(self::once())
            ->method('__invoke')
            ->with($this->platformRepository, $composerPackage)
            ->willReturn([
                new DependencyStatus('lib-bar', $versionParser->parseConstraints('^1.0'), null),
            ]);

        $systemDependenciesWereInstalled = ($scanner)($this->composer, $this->targetPlatform, $this->platformRepository, $request, true);

        self::assertTrue($systemDependenciesWereInstalled);

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
            ->with($this->composer, $this->targetPlatform, $this->platformRepository, $request, true)
            ->willReturn(new ResolvedPackageRequest($piePackage, $request));

        $versionParser = new VersionParser();

        $this->fetchDependencyStatuses->expects(self::once())
            ->method('__invoke')
            ->with($this->platformRepository, $composerPackage)
            ->willReturn([
                new DependencyStatus('lib-bar', $versionParser->parseConstraints('^1.0'), null),
            ]);

        $systemDependenciesWereInstalled = ($scanner)($this->composer, $this->targetPlatform, $this->platformRepository, $request, false);

        self::assertFalse($systemDependenciesWereInstalled);

        $outputString = $this->io->getOutput();
        self::assertStringContainsString('Extension foo/foo has unmet dependencies: lib-bar', $outputString);
        self::assertStringContainsString('Adding test package libbar-dev to be installed for lib-bar', $outputString);
        self::assertStringContainsString('You are not running in interactive mode, and you did not provide the --auto-install-system-dependencies flag.', $outputString);
        self::assertStringContainsString('You may need to run: echo "fake installing libbar-dev"', $outputString);
        self::assertStringNotContainsString('Need to install missing system dependencies', $outputString);
    }

    /** @return array<non-empty-string, array{0: non-empty-string, 1: PackageManager, 2: non-empty-string}> */
    public static function defaultDefinitions(): array
    {
        return [
            'sodium with apt' => ['sodium', PackageManager::Apt, 'libsodium-dev'],
            'sodium with apk' => ['sodium', PackageManager::Apk, 'libsodium-dev'],
            'sodium with dnf' => ['sodium', PackageManager::Dnf, 'pkgconfig(libsodium)'],
            'sodium with microdnf' => ['sodium', PackageManager::Microdnf, 'pkgconfig(libsodium)'],
            'sodium with yum' => ['sodium', PackageManager::Yum, 'pkgconfig(libsodium)'],
            'jpeg with apt' => ['jpeg', PackageManager::Apt, 'libjpeg-dev'],
            'jpeg with apk' => ['jpeg', PackageManager::Apk, 'libjpeg-turbo-dev'],
            'jpeg with dnf' => ['jpeg', PackageManager::Dnf, 'pkgconfig(libjpeg)'],
            'jpeg with microdnf' => ['jpeg', PackageManager::Microdnf, 'pkgconfig(libjpeg)'],
            'jpeg with yum' => ['jpeg', PackageManager::Yum, 'pkgconfig(libjpeg)'],
            'zip with apt' => ['zip', PackageManager::Apt, 'libzip-dev'],
            'zip with apk' => ['zip', PackageManager::Apk, 'libzip-dev'],
            'zip with dnf' => ['zip', PackageManager::Dnf, 'pkgconfig(libzip)'],
            'zip with microdnf' => ['zip', PackageManager::Microdnf, 'pkgconfig(libzip)'],
            'zip with yum' => ['zip', PackageManager::Yum, 'pkgconfig(libzip)'],
            'xslt with apt' => ['xslt', PackageManager::Apt, 'libxslt1-dev'],
            'xslt with apk' => ['xslt', PackageManager::Apk, 'libxslt-dev'],
            'xslt with dnf' => ['xslt', PackageManager::Dnf, 'pkgconfig(libxslt)'],
            'xslt with microdnf' => ['xslt', PackageManager::Microdnf, 'pkgconfig(libxslt)'],
            'xslt with yum' => ['xslt', PackageManager::Yum, 'pkgconfig(libxslt)'],
            'ffi with apt' => ['ffi', PackageManager::Apt, 'libffi-dev'],
            'ffi with apk' => ['ffi', PackageManager::Apk, 'libffi-dev'],
            'ffi with dnf' => ['ffi', PackageManager::Dnf, 'pkgconfig(libffi)'],
            'ffi with microdnf' => ['ffi', PackageManager::Microdnf, 'pkgconfig(libffi)'],
            'ffi with yum' => ['ffi', PackageManager::Yum, 'pkgconfig(libffi)'],
            'curl with apt' => ['curl', PackageManager::Apt, 'libcurl4-openssl-dev'],
            'curl with apk' => ['curl', PackageManager::Apk, 'curl-dev'],
            'curl with dnf' => ['curl', PackageManager::Dnf, 'pkgconfig(libcurl)'],
            'curl with microdnf' => ['curl', PackageManager::Microdnf, 'pkgconfig(libcurl)'],
            'curl with yum' => ['curl', PackageManager::Yum, 'pkgconfig(libcurl)'],
            'gpgme with apt' => ['gpgme', PackageManager::Apt, 'libgpgme-dev'],
            'gpgme with apk' => ['gpgme', PackageManager::Apk, 'gpgme-dev'],
            'gpgme with dnf' => ['gpgme', PackageManager::Dnf, 'pkgconfig(gpgme)'],
            'gpgme with microdnf' => ['gpgme', PackageManager::Microdnf, 'pkgconfig(gpgme)'],
            'gpgme with yum' => ['gpgme', PackageManager::Yum, 'pkgconfig(gpgme)'],
            'pam with apt' => ['pam', PackageManager::Apt, 'libpam0g-dev'],
            'pam with apk' => ['pam', PackageManager::Apk, 'linux-pam-dev'],
            'pam with dnf' => ['pam', PackageManager::Dnf, 'pkgconfig(pam)'],
            'pam with microdnf' => ['pam', PackageManager::Microdnf, 'pkgconfig(pam)'],
            'pam with yum' => ['pam', PackageManager::Yum, 'pkgconfig(pam)'],
            'yaml with apt' => ['yaml', PackageManager::Apt, 'libyaml-dev'],
            'yaml with apk' => ['yaml', PackageManager::Apk, 'yaml-dev'],
            'yaml with dnf' => ['yaml', PackageManager::Dnf, 'pkgconfig(yaml-0.1)'],
            'yaml with microdnf' => ['yaml', PackageManager::Microdnf, 'pkgconfig(yaml-0.1)'],
            'yaml with yum' => ['yaml', PackageManager::Yum, 'pkgconfig(yaml-0.1)'],
            'uv with apt' => ['uv', PackageManager::Apt, 'libuv1-dev'],
            'uv with apk' => ['uv', PackageManager::Apk, 'libuv-dev'],
            'uv with dnf' => ['uv', PackageManager::Dnf, 'pkgconfig(libuv)'],
            'uv with microdnf' => ['uv', PackageManager::Microdnf, 'pkgconfig(libuv)'],
            'uv with yum' => ['uv', PackageManager::Yum, 'pkgconfig(libuv)'],
            'uuid with apt' => ['uuid', PackageManager::Apt, 'uuid-dev'],
            'uuid with apk' => ['uuid', PackageManager::Apk, 'util-linux-dev'],
            'uuid with dnf' => ['uuid', PackageManager::Dnf, 'pkgconfig(uuid)'],
            'uuid with microdnf' => ['uuid', PackageManager::Microdnf, 'pkgconfig(uuid)'],
            'uuid with yum' => ['uuid', PackageManager::Yum, 'pkgconfig(uuid)'],
            'rdkafka with apt' => ['rdkafka', PackageManager::Apt, 'librdkafka-dev'],
            'rdkafka with apk' => ['rdkafka', PackageManager::Apk, 'librdkafka-dev'],
            'rdkafka with dnf' => ['rdkafka', PackageManager::Dnf, 'pkgconfig(rdkafka)'],
            'rdkafka with microdnf' => ['rdkafka', PackageManager::Microdnf, 'pkgconfig(rdkafka)'],
            'rdkafka with yum' => ['rdkafka', PackageManager::Yum, 'pkgconfig(rdkafka)'],
            'zlib with apt' => ['zlib', PackageManager::Apt, 'zlib1g-dev'],
            'zlib with apk' => ['zlib', PackageManager::Apk, 'zlib-dev'],
            'zlib with dnf' => ['zlib', PackageManager::Dnf, 'pkgconfig(zlib)'],
            'zlib with microdnf' => ['zlib', PackageManager::Microdnf, 'pkgconfig(zlib)'],
            'zlib with yum' => ['zlib', PackageManager::Yum, 'pkgconfig(zlib)'],
            'sasl with apt' => ['sasl', PackageManager::Apt, 'libsasl2-dev'],
            'sasl with apk' => ['sasl', PackageManager::Apk, 'cyrus-sasl-dev'],
            'sasl with dnf' => ['sasl', PackageManager::Dnf, 'pkgconfig(libsasl2)'],
            'sasl with microdnf' => ['sasl', PackageManager::Microdnf, 'pkgconfig(libsasl2)'],
            'sasl with yum' => ['sasl', PackageManager::Yum, 'pkgconfig(libsasl2)'],
            'memcached with apt' => ['memcached', PackageManager::Apt, 'libmemcached-dev'],
            'memcached with apk' => ['memcached', PackageManager::Apk, 'libmemcached-dev'],
            'memcached with dnf' => ['memcached', PackageManager::Dnf, 'pkgconfig(libmemcached)'],
            'memcached with microdnf' => ['memcached', PackageManager::Microdnf, 'pkgconfig(libmemcached)'],
            'memcached with yum' => ['memcached', PackageManager::Yum, 'pkgconfig(libmemcached)'],
            'imagemagick with apt' => ['imagemagick', PackageManager::Apt, 'libmagickwand-dev'],
            'imagemagick with apk' => ['imagemagick', PackageManager::Apk, 'imagemagick-dev'],
            'imagemagick with dnf' => ['imagemagick', PackageManager::Dnf, 'pkgconfig(MagickWand)'],
            'imagemagick with microdnf' => ['imagemagick', PackageManager::Microdnf, 'pkgconfig(MagickWand)'],
            'imagemagick with yum' => ['imagemagick', PackageManager::Yum, 'pkgconfig(MagickWand)'],
            'event with apt' => ['event', PackageManager::Apt, 'libevent-dev'],
            'event with apk' => ['event', PackageManager::Apk, 'libevent-dev'],
            'event with dnf' => ['event', PackageManager::Dnf, 'pkgconfig(libevent)'],
            'event with microdnf' => ['event', PackageManager::Microdnf, 'pkgconfig(libevent)'],
            'event with yum' => ['event', PackageManager::Yum, 'pkgconfig(libevent)'],
            'openssl with apt' => ['openssl', PackageManager::Apt, 'libssl-dev'],
            'openssl with apk' => ['openssl', PackageManager::Apk, 'openssl-dev'],
            'openssl with dnf' => ['openssl', PackageManager::Dnf, 'pkgconfig(openssl)'],
            'openssl with microdnf' => ['openssl', PackageManager::Microdnf, 'pkgconfig(openssl)'],
            'openssl with yum' => ['openssl', PackageManager::Yum, 'pkgconfig(openssl)'],
            'ssh2 with apt' => ['ssh2', PackageManager::Apt, 'libssh2-1-dev'],
            'ssh2 with apk' => ['ssh2', PackageManager::Apk, 'libssh2-dev'],
            'ssh2 with dnf' => ['ssh2', PackageManager::Dnf, 'pkgconfig(libssh2)'],
            'ssh2 with microdnf' => ['ssh2', PackageManager::Microdnf, 'pkgconfig(libssh2)'],
            'ssh2 with yum' => ['ssh2', PackageManager::Yum, 'pkgconfig(libssh2)'],
        ];
    }

    #[DataProvider('defaultDefinitions')]
    public function testDefaultDefinitionProposesSystemPackageForMissingLibrary(string $alias, PackageManager $packageManager, string $expectedSystemPackage): void
    {
        $scanner = new PrescanSystemDependencies(
            $this->dependencyResolver,
            $this->fetchDependencyStatuses,
            SystemDependenciesDefinition::default(),
            $packageManager,
            $this->io,
        );

        $request         = new RequestedPackageAndVersion('foo/foo', null);
        $composerPackage = new CompletePackage('foo/foo', '1.0.0.0', '1.0.0');
        $this->dependencyResolver->method('__invoke')
            ->willReturn(new ResolvedPackageRequest(Package::fromComposerCompletePackage($composerPackage), $request));

        $this->fetchDependencyStatuses->method('__invoke')
            ->willReturn([
                new DependencyStatus('lib-' . $alias, (new VersionParser())->parseConstraints('*'), null),
            ]);

        ($scanner)($this->composer, $this->targetPlatform, $this->platformRepository, $request, false);

        self::assertStringContainsString(
            'You may need to run: ' . implode(' ', $packageManager->installCommand([$expectedSystemPackage])),
            $this->io->getOutput(),
        );
    }
}
