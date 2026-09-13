<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\QuieterConsoleIO;
use Php\Pie\File\BinaryFile;
use Php\Pie\File\FullPathToSelf;
use Php\Pie\SelfManage\Update\FetchPieReleaseFromGitHub;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Php\Pie\SelfManage\Verify\VerifyPieReleaseUsingAttestation;
use Php\Pie\Util\Emoji;
use Php\Pie\Util\PieVersion;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function sprintf;

#[AsCommand(
    name: 'self-verify',
    description: 'Self verify PIE',
)]
final class SelfVerifyCommand extends Command
{
    private const ARGUMENT_VERSION = 'version';

    /** @param non-empty-string $githubApiBaseUrl */
    public function __construct(
        private readonly string $githubApiBaseUrl,
        private readonly FullPathToSelf $fullPathToSelf,
        private readonly IOInterface $io,
        private readonly QuieterConsoleIO $quieterConsoleIo,
        private readonly ContainerInterface $container,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        parent::configure();

        CommandHelper::configurePhpConfigOptions($this);
        $this->addArgument(
            self::ARGUMENT_VERSION,
            InputArgument::OPTIONAL,
            'The version of PIE you expect to be running (e.g. 1.4.4 or nightly)',
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! PieVersion::isPharBuild()) {
            $this->io->writeError('<comment>Aborting! You are not running a PHAR, cannot self-verify.</comment>');

            return Command::FAILURE;
        }

        $expectedVersion = (string) $input->getArgument(self::ARGUMENT_VERSION);

        if ($expectedVersion === '') {
            $expectedVersion = PieVersion::get();
            $this->io->write(sprintf('<comment>No version specified, verifying against the version this PHAR claims to be (%s).</comment>', $expectedVersion));
        }

        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $this->io);

        CommandHelper::applyNoCacheOptionIfSet($input, $this->io);

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            PieComposerRequest::noOperation(
                new NullIO(),
                $targetPlatform,
            ),
        );

        $fetchLatestPieRelease = FetchPieReleaseFromGitHub::factory(
            $this->quieterConsoleIo,
            $composer->getConfig(),
            $this->githubApiBaseUrl,
        );

        $latestRelease = new ReleaseMetadata($expectedVersion, 'blah');
        $pharFilename  = BinaryFile::fromFileWithSha256Checksum(($this->fullPathToSelf)());
        $verifyPiePhar = VerifyPieReleaseUsingAttestation::factory(
            $fetchLatestPieRelease,
            $this->quieterConsoleIo,
            $composer->getConfig(),
            $this->githubApiBaseUrl,
        );

        try {
            $verifyPiePhar->verify($latestRelease, $pharFilename, $this->io);
        } catch (Throwable $verificationFailure) {
            $this->io->writeError(sprintf(
                '<error>❌ Failed to verify that this PIE binary is the authentic release %s: %s</error>',
                $latestRelease->tag,
                $verificationFailure->getMessage(),
            ));
            $this->getApplication()?->renderThrowable(
                $verificationFailure,
                $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output,
            );

            return Command::FAILURE;
        }

        $this->io->write(sprintf(
            '<info>%s This is an authentic PIE release for version %s.</info>',
            Emoji::GREEN_CHECKMARK,
            $latestRelease->tag,
        ));

        return Command::SUCCESS;
    }
}
