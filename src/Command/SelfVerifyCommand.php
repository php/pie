<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\Util\AuthHelper;
use Composer\Util\HttpDownloader;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\QuieterConsoleIO;
use Php\Pie\File\BinaryFile;
use Php\Pie\File\FullPathToSelf;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Php\Pie\SelfManage\Verify\FailedToVerifyRelease;
use Php\Pie\SelfManage\Verify\VerifyPieReleaseUsingAttestation;
use Php\Pie\Util\PieVersion;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

#[AsCommand(
    name: 'self-verify',
    description: 'Self verify PIE',
)]
final class SelfVerifyCommand extends Command
{
    /** @param non-empty-string $githubApiBaseUrl */
    public function __construct(
        private readonly string $githubApiBaseUrl,
        private readonly QuieterConsoleIO $io,
        private readonly ContainerInterface $container,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        parent::configure();

        CommandHelper::configurePhpConfigOptions($this);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! PieVersion::isPharBuild()) {
            $output->writeln('<comment>Aborting! You are not running a PHAR, cannot self-update.</comment>');

            return 1;
        }

        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $output);
        $composer       = PieComposerFactory::createPieComposer(
            $this->container,
            PieComposerRequest::noOperation(
                $output,
                $targetPlatform,
            ),
        );
        $httpDownloader = new HttpDownloader($this->io, $composer->getConfig());
        $authHelper     = new AuthHelper($this->io, $composer->getConfig());
        $latestRelease  = new ReleaseMetadata(PieVersion::get(), 'blah');
        $pharFilename   = BinaryFile::fromFileWithSha256Checksum((new FullPathToSelf())());
        $verifyPiePhar  = VerifyPieReleaseUsingAttestation::factory($this->githubApiBaseUrl, $httpDownloader, $authHelper);

        try {
            $verifyPiePhar->verify($latestRelease, $pharFilename, $output);
        } catch (FailedToVerifyRelease $failedToVerifyRelease) {
            $output->writeln(sprintf(
                '<error>❌ Failed to verify the pie.phar release %s: %s</error>',
                $latestRelease->tag,
                $failedToVerifyRelease->getMessage(),
            ));

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            '<error>✅ You are running an authentic PIE version %s.</error>',
            $latestRelease->tag,
        ));

        return Command::SUCCESS;
    }
}
