<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\ComposerIntegration;

use Composer\Composer;
use Composer\IO\NullIO;
use Composer\Json\JsonFile;
use Composer\Package\CompletePackage;
use Composer\Package\Dumper\ArrayDumper;
use Php\Pie\ComposerIntegration\ComposerIntegrationHandler;
use Php\Pie\ComposerIntegration\InstalledJsonMetadata;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieJsonEditor;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\Container;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\DependencyResolver\ResolvedPackageRequest;
use Php\Pie\Platform;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\PieIntegrationTest\Command\IsolatedWorkingDirectoryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use Symfony\Component\Console\Output\BufferedOutput;

use function file_exists;
use function Safe\copy;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\hash_file;
use function Safe\json_decode;
use function Safe\mkdir;
use function Safe\unlink;

use const DIRECTORY_SEPARATOR;

#[CoversClass(ComposerIntegrationHandler::class)]
final class ComposerIntegrationHandlerTest extends IsolatedWorkingDirectoryTestCase
{
    private const PACKAGE_NAME    = 'asgrim/example-pie-extension';
    private const EXTENSION_NAME  = 'example_pie_extension';
    private const VERSION_CURRENT = '2.0.9';
    private const VERSION_OTHER   = '2.0.2';

    private TargetPlatform $targetPlatform;
    private BufferedOutput $capturedOutput;
    private ComposerIntegrationHandler $handler;
    private string|null $createdFakeBinaryPath = null;

    protected function tearDown(): void
    {
        if ($this->createdFakeBinaryPath !== null && file_exists($this->createdFakeBinaryPath)) {
            unlink($this->createdFakeBinaryPath);
        }

        parent::tearDown();
    }

    public function setUp(): void
    {
        parent::setUp();

        $phpBinaryPath        = PhpBinaryPath::fromCurrentProcess();
        $this->targetPlatform = TargetPlatform::fromPhpBinaryPath($phpBinaryPath, null, null);

        $this->createdFakeBinaryPath = $this->extensionBinaryPath();
        file_put_contents($this->createdFakeBinaryPath, '');

        $this->capturedOutput = new BufferedOutput(BufferedOutput::VERBOSITY_VERBOSE);

        $this->handler = Container::testFactory($this->capturedOutput)->get(ComposerIntegrationHandler::class);
    }

    public function testRunInstallInvokesInstallerForNewPackage(): void
    {
        $composer = $this->makeComposer(self::VERSION_CURRENT);

        $this->handler->runInstall(
            [$this->makeResolvedRequest(self::VERSION_CURRENT)],
            $composer,
            $this->targetPlatform,
            false,
            false,
        );

        $output = $this->capturedOutput->fetch();
        self::assertStringNotContainsString('is already installed and verified', $output);
        self::assertStringNotContainsString('adding to install candidates', $output);
    }

    public function testRunInstallSkipsAlreadyVerifiedPackageAtSameVersion(): void
    {
        PieJsonEditor::fromTargetPlatform($this->targetPlatform)
            ->ensureExists()
            ->addRequire(self::PACKAGE_NAME, self::VERSION_CURRENT);
        $this->setUpInstalledJson(self::VERSION_CURRENT);
        $this->setUpLockFile();

        $composer = $this->makeComposer(self::VERSION_CURRENT);

        $this->handler->runInstall(
            [$this->makeResolvedRequest(self::VERSION_CURRENT)],
            $composer,
            $this->targetPlatform,
            false,
            false,
        );

        $output = $this->capturedOutput->fetch();
        self::assertStringContainsString(
            self::PACKAGE_NAME . ' (' . self::EXTENSION_NAME . ') is already installed and verified',
            $output,
        );
        self::assertStringContainsString('Nothing to install, update or remove', $output);
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testRunInstallReinstallsVerifiedPackageWhenVersionDiffers(): void
    {
        PieJsonEditor::fromTargetPlatform($this->targetPlatform)
            ->ensureExists()
            ->addRequire(self::PACKAGE_NAME, self::VERSION_CURRENT);
        $this->setUpInstalledJson(self::VERSION_CURRENT);
        $this->setUpLockFile();

        $composer = $this->makeComposer(self::VERSION_OTHER);

        $this->handler->runInstall(
            [$this->makeResolvedRequest(self::VERSION_OTHER)],
            $composer,
            $this->targetPlatform,
            false,
            false,
        );

        $output = $this->capturedOutput->fetch();
        self::assertStringContainsString(
            self::PACKAGE_NAME . ' (' . self::EXTENSION_NAME . ') is at ' . self::VERSION_CURRENT . ' but ' . self::VERSION_OTHER . ' was installed, adding to install candidates',
            $output,
        );
        self::assertStringNotContainsString('is already installed and verified', $output);
    }

    public function testRunInstallWithNoResolvedPackagesTreatsLockedPackagesAsExtensionNames(): void
    {
        PieJsonEditor::fromTargetPlatform($this->targetPlatform)
            ->ensureExists()
            ->addRequire(self::PACKAGE_NAME, self::VERSION_CURRENT);
        $this->setUpInstalledJson(self::VERSION_CURRENT);
        $this->setUpLockFile();

        $composer = PieComposerFactory::createPieComposer(
            Container::testFactory($this->capturedOutput),
            new PieComposerRequest(
                new NullIO(),
                $this->targetPlatform,
                [],
                PieOperation::Install,
                [],
                false,
                installAllPackages: true,
            ),
        );

        $this->handler->runInstall(
            [],
            $composer,
            $this->targetPlatform,
            false,
            false,
        );

        $output = $this->capturedOutput->fetch();
        self::assertStringContainsString(
            self::PACKAGE_NAME . ' (' . self::EXTENSION_NAME . ') is already installed and verified',
            $output,
        );
    }

    public function testRunUninstallRemovesPackageFromPieJson(): void
    {
        PieJsonEditor::fromTargetPlatform($this->targetPlatform)
            ->ensureExists()
            ->addRequire(self::PACKAGE_NAME, self::VERSION_CURRENT);
        $this->setUpInstalledJson(self::VERSION_CURRENT);
        $this->setUpLockFile();

        $composer = $this->makeComposer(self::VERSION_CURRENT);

        $this->handler->runUninstall(
            [$this->makeResolvedRequest(self::VERSION_CURRENT)],
            $composer,
            $this->targetPlatform,
        );

        $pieJson = json_decode(
            file_get_contents(Platform::getPieWorkingDirectory($this->targetPlatform) . '/pie.json'),
            true,
        );
        self::assertIsArray($pieJson);
        self::assertArrayNotHasKey('require', $pieJson);
    }

    private function extensionBinaryPath(): string
    {
        $isWindows = $this->targetPlatform->operatingSystem === OperatingSystem::Windows;

        return $this->targetPlatform->phpBinaryPath->extensionPath() . DIRECTORY_SEPARATOR . ($isWindows ? 'php_' : '') . self::EXTENSION_NAME . ($isWindows ? '.dll' : '.so');
    }

    /** @param non-empty-string $version */
    private function makeComposerPackage(string $version): CompletePackage
    {
        $sha                 = '963c8d70c57c23fa2098e499a0ebffabb64748b3';
        $extensionBinaryPath = $this->extensionBinaryPath();

        $package = new CompletePackage(self::PACKAGE_NAME, $version . '.0', $version);
        $package->setType('php-ext');
        $package->setPhpExt(['extension-name' => 'ext-' . self::EXTENSION_NAME]);
        $package->setDistType('zip');
        $package->setInstallationSource('dist');
        $package->setDistUrl('https://api.github.com/repos/asgrim/example-pie-extension/zipball/' . $sha);
        $package->setDistReference($sha);
        $package->setSourceType('git');
        $package->setSourceUrl('https://github.com/asgrim/example-pie-extension.git');
        $package->setSourceReference($sha);
        $package->setExtra([
            InstalledJsonMetadata::KEY_TARGET_PLATFORM_PHP_VERSION => $this->targetPlatform->phpBinaryPath->version(),
            InstalledJsonMetadata::KEY_BUILT_BINARY                => $extensionBinaryPath,
            InstalledJsonMetadata::KEY_INSTALLED_BINARY            => $extensionBinaryPath,
            InstalledJsonMetadata::KEY_BINARY_CHECKSUM             => hash_file('sha256', $extensionBinaryPath),
        ]);

        return $package;
    }

    /** @param non-empty-string $version */
    private function setUpInstalledJson(string $version): void
    {
        mkdir(Platform::getPieWorkingDirectory($this->targetPlatform) . '/vendor/composer', 0755, true);

        (new JsonFile(Platform::getPieWorkingDirectory($this->targetPlatform) . '/vendor/composer/installed.json'))->write([
            'packages'          => [(new ArrayDumper())->dump($this->makeComposerPackage($version))],
            'dev'               => true,
            'dev-package-names' => [],
        ]);
    }

    private function setUpLockFile(): void
    {
        copy(__DIR__ . '/../../assets/pie-install-from-lock/pie.lock', Platform::getPieWorkingDirectory($this->targetPlatform) . '/pie.lock');
    }

    /** @param non-empty-string $version */
    private function makeResolvedRequest(string $version): ResolvedPackageRequest
    {
        return new ResolvedPackageRequest(
            Package::fromComposerCompletePackage($this->makeComposerPackage($version)),
            new RequestedPackageAndVersion(self::PACKAGE_NAME, $version),
        );
    }

    /** @param non-empty-string $version */
    private function makeComposer(string $version): Composer
    {
        return PieComposerFactory::createPieComposer(
            Container::testFactory($this->capturedOutput),
            new PieComposerRequest(
                new NullIO(),
                $this->targetPlatform,
                [new RequestedPackageAndVersion(self::PACKAGE_NAME, $version)],
                PieOperation::Install,
                [],
                false,
            ),
        );
    }
}
