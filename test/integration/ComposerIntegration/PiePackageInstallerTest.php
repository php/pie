<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\ComposerIntegration;

use Composer\IO\NullIO;
use Composer\Package\CompletePackage;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PiePackageInstaller;
use Php\Pie\Container;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Util\Process;
use Php\PieIntegrationTest\Command\IsolatedWorkingDirectoryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Process\Process as SymfonyProcess;

use function file_exists;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\mkdir;
use function sys_get_temp_dir;
use function uniqid;

#[CoversClass(PiePackageInstaller::class)]
final class PiePackageInstallerTest extends IsolatedWorkingDirectoryTestCase
{
    private const PACKAGE_NAME = 'asgrim/example-pie-extension';

    private string $gitRepositoryPath;

    public function setUp(): void
    {
        parent::setUp();

        $this->gitRepositoryPath = sys_get_temp_dir() . '/pie-test-git-repository-' . uniqid();
        mkdir($this->gitRepositoryPath, 0755, true);
        Process::run(['git', 'init', '--quiet', '--initial-branch=main'], $this->gitRepositoryPath);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->gitRepositoryPath)) {
            (new SymfonyProcess(['rm', '-rf', $this->gitRepositoryPath]))->run();
        }

        parent::tearDown();
    }

    public function testUpdateOfSourceInstalledPackageWhenSourceWasRemovedAfterInstall(): void
    {
        $initialReference = $this->commitConfigM4('initial');
        $targetReference  = $this->commitConfigM4('target');

        $composer = PieComposerFactory::createPieComposer(
            Container::testFactory(),
            PieComposerRequest::noOperation(
                new NullIO(),
                TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess(), null, null),
            ),
        );

        $installer = $composer->getInstallationManager()->getInstaller('php-ext');
        self::assertInstanceOf(PiePackageInstaller::class, $installer);

        $initialPackage  = $this->makeDevMainPackage($initialReference);
        $targetPackage   = $this->makeDevMainPackage($targetReference);
        $localRepository = $composer->getRepositoryManager()->getLocalRepository();
        $localRepository->addPackage($initialPackage);

        $promise = $installer->update($localRepository, $initialPackage, $targetPackage);
        self::assertNotNull($promise);
        $composer->getLoop()->wait([$promise]);

        self::assertSame('target', file_get_contents($installer->getInstallPath($targetPackage) . '/config.m4'));
    }

    private function commitConfigM4(string $content): string
    {
        file_put_contents($this->gitRepositoryPath . '/config.m4', $content);
        Process::run(['git', 'add', 'config.m4'], $this->gitRepositoryPath);
        Process::run(
            ['git', '-c', 'user.name=PIE Test', '-c', 'user.email=pie-test@example.com', '-c', 'commit.gpgsign=false', 'commit', '--quiet', '-m', $content],
            $this->gitRepositoryPath,
        );

        return Process::run(['git', 'rev-parse', 'HEAD'], $this->gitRepositoryPath);
    }

    private function makeDevMainPackage(string $reference): CompletePackage
    {
        $package = new CompletePackage(self::PACKAGE_NAME, 'dev-main', 'dev-main');
        $package->setType('php-ext');
        $package->setInstallationSource('source');
        $package->setSourceType('git');
        $package->setSourceUrl($this->gitRepositoryPath);
        $package->setSourceReference($reference);

        return $package;
    }
}
