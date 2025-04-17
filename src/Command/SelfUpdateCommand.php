<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\Semver\Semver;
use Composer\Util\AuthHelper;
use Composer\Util\HttpDownloader;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\QuieterConsoleIO;
use Php\Pie\File\FullPathToSelf;
use Php\Pie\File\SudoFilePut;
use Php\Pie\SelfManage\Update\FetchPieReleaseFromGitHub;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Php\Pie\SelfManage\Verify\FailedToVerifyRelease;
use Php\Pie\SelfManage\Verify\VerifyPieReleaseUsingAttestation;
use Php\Pie\Util\PieVersion;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function file_get_contents;
use function preg_match;
use function sprintf;
use function unlink;

#[AsCommand(
    name: 'self-update',
    description: 'Self update PIE',
)]
final class SelfUpdateCommand extends Command
{
    private const OPTION_NIGHTLY_UPDATE = 'nightly';

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
        $this->addOption(
            self::OPTION_NIGHTLY_UPDATE,
            null,
            InputOption::VALUE_NONE,
            'Update to the latest nightly version.',
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! PieVersion::isPharBuild()) {
            $output->writeln('<comment>Aborting! You are not running a PHAR, cannot self-update.</comment>');

            return 1;
        }

        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $output);

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            PieComposerRequest::noOperation(
                $output,
                $targetPlatform,
            ),
        );

        $httpDownloader        = new HttpDownloader($this->io, $composer->getConfig());
        $authHelper            = new AuthHelper($this->io, $composer->getConfig());
        $fetchLatestPieRelease = new FetchPieReleaseFromGitHub($this->githubApiBaseUrl, $httpDownloader, $authHelper);
        $verifyPiePhar         = VerifyPieReleaseUsingAttestation::factory($this->githubApiBaseUrl, $httpDownloader, $authHelper);

        if ($input->hasOption(self::OPTION_NIGHTLY_UPDATE) && $input->getOption(self::OPTION_NIGHTLY_UPDATE)) {
            $latestRelease = new ReleaseMetadata(
                'nightly',
                'https://php.github.io/pie/pie-nightly.phar',
            );

            $output->writeln('Downloading the latest nightly release.');
        } else {
            $latestRelease = $fetchLatestPieRelease->latestReleaseMetadata();
            $pieVersion    = PieVersion::get();

            if (preg_match('/^(?<tag>.+)@(?<hash>[a-f0-9]{7})$/', $pieVersion, $matches)) {
                // Have to change the version to something the Semver library understands
                $pieVersion = sprintf('dev-main#%s', $matches['hash']);
                $output->writeln(sprintf(
                    'It looks like you are running a nightly build; if you want to get the newest nightly, specify the --%s flag.',
                    self::OPTION_NIGHTLY_UPDATE,
                ));
            }

            $output->writeln(sprintf('You are currently running PIE version %s', $pieVersion));

            if (! Semver::satisfies($latestRelease->tag, '> ' . $pieVersion)) {
                $output->writeln('<info>You already have the latest version 😍</info>');

                return Command::SUCCESS;
            }

            $output->writeln(
                sprintf('Newer version %s found, going to update you... ⏳', $latestRelease->tag),
                OutputInterface::VERBOSITY_VERBOSE,
            );
        }

        $pharFilename = $fetchLatestPieRelease->downloadContent($latestRelease);

        $output->writeln(
            sprintf('Verifying release with digest sha256:%s...', $pharFilename->checksum),
            OutputInterface::VERBOSITY_VERBOSE,
        );

        try {
            $verifyPiePhar->verify($latestRelease, $pharFilename, $output);
        } catch (FailedToVerifyRelease $failedToVerifyRelease) {
            $output->writeln(sprintf(
                '<error>❌ Failed to verify the pie.phar release %s: %s</error>',
                $latestRelease->tag,
                $failedToVerifyRelease->getMessage(),
            ));

            $output->writeln('This means I could not verify that the PHAR we tried to update to was authentic, so I am aborting the self-update.');
            unlink($pharFilename->filePath);

            return Command::FAILURE;
        }

        $fullPathToSelf = (new FullPathToSelf())();
        $output->writeln(
            sprintf('Writing new version to %s', $fullPathToSelf),
            OutputInterface::VERBOSITY_VERBOSE,
        );
        SudoFilePut::contents($fullPathToSelf, file_get_contents($pharFilename->filePath));

        $output->writeln('<info>✅ PIE has been upgraded to ' . $latestRelease->tag . '</info>');

        return Command::SUCCESS;
    }
}
