<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\Util\AuthHelper;
use Composer\Util\HttpDownloader;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\QuieterConsoleIO;
use Php\Pie\File\FullPathToSelf;
use Php\Pie\File\SudoFilePut;
use Php\Pie\Platform;
use Php\Pie\SelfManage\Update\Channel;
use Php\Pie\SelfManage\Update\FetchPieReleaseFromGitHub;
use Php\Pie\SelfManage\Update\PiePharMissingFromLatestRelease;
use Php\Pie\SelfManage\Update\ReleaseIsNewer;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Php\Pie\SelfManage\Verify\FailedToVerifyRelease;
use Php\Pie\SelfManage\Verify\VerifyPieReleaseUsingAttestation;
use Php\Pie\Settings;
use Php\Pie\Util\Emoji;
use Php\Pie\Util\PieVersion;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function file_get_contents;
use function sprintf;
use function unlink;

#[AsCommand(
    name: 'self-update',
    description: 'Self update PIE',
)]
final class SelfUpdateCommand extends Command
{
    private const OPTION_STABLE_UPDATE  = 'stable';
    private const OPTION_PREVIEW_UPDATE = 'preview';
    private const OPTION_NIGHTLY_UPDATE = 'nightly';

    /** @param non-empty-string $githubApiBaseUrl */
    public function __construct(
        private readonly string $githubApiBaseUrl,
        private readonly QuieterConsoleIO $io,
        private readonly ContainerInterface $container,
        private readonly FullPathToSelf $fullPathToSelf,
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
        $this->addOption(
            self::OPTION_PREVIEW_UPDATE,
            null,
            InputOption::VALUE_NONE,
            'Update to the latest preview version.',
        );
        $this->addOption(
            self::OPTION_STABLE_UPDATE,
            null,
            InputOption::VALUE_NONE,
            'Update to the latest stable version.',
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! PieVersion::isPharBuild()) {
            $output->writeln('<comment>Aborting! You are not running a PHAR, cannot self-update.</comment>');

            return Command::FAILURE;
        }

        $settings      = new Settings(Platform::getPieBaseWorkingDirectory());
        $updateChannel = $settings->updateChannel();

        if ($input->hasOption(self::OPTION_NIGHTLY_UPDATE) && $input->getOption(self::OPTION_NIGHTLY_UPDATE)) {
            $settings->changeUpdateChannel(Channel::Nightly);
            $updateChannel = Channel::Nightly;
        } elseif ($input->hasOption(self::OPTION_PREVIEW_UPDATE) && $input->getOption(self::OPTION_PREVIEW_UPDATE)) {
            $settings->changeUpdateChannel(Channel::Preview);
            $updateChannel = Channel::Preview;
        } elseif ($input->hasOption(self::OPTION_STABLE_UPDATE) && $input->getOption(self::OPTION_STABLE_UPDATE)) {
            $settings->changeUpdateChannel(Channel::Stable);
            $updateChannel = Channel::Stable;
        }

        $output->writeln(sprintf('Updating using the <info>%s</> channel.', $updateChannel->value));

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
        $verifyPiePhar         = VerifyPieReleaseUsingAttestation::factory();

        if ($updateChannel === Channel::Nightly) {
            $latestRelease = new ReleaseMetadata(
                'nightly',
                'https://php.github.io/pie/pie-nightly.phar',
            );

            $output->writeln('Downloading the latest nightly release.');
        } else {
            try {
                $latestRelease = $fetchLatestPieRelease->latestReleaseMetadata($updateChannel);
            } catch (PiePharMissingFromLatestRelease $piePharMissingFromLatestRelease) {
                $output->writeln(sprintf('<error>%s</error>', $piePharMissingFromLatestRelease->getMessage()));

                return Command::FAILURE;
            }

            $pieVersion = PieVersion::get();

            $output->writeln(sprintf('You are currently running PIE version %s', $pieVersion));

            if (! ReleaseIsNewer::forChannel($updateChannel, $pieVersion, $latestRelease)) {
                $output->writeln(sprintf(
                    '<info>You already have the latest version for the %s channel 😍</info>',
                    $updateChannel->value,
                ));

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

        $fullPathToSelf = ($this->fullPathToSelf)();
        $output->writeln(
            sprintf('Writing new version to %s', $fullPathToSelf),
            OutputInterface::VERBOSITY_VERBOSE,
        );
        SudoFilePut::contents($fullPathToSelf, file_get_contents($pharFilename->filePath));
        unlink($pharFilename->filePath);

        $output->writeln(sprintf(
            '<info>%s PIE has been upgraded to %s</info>',
            Emoji::GREEN_CHECKMARK,
            $latestRelease->tag,
        ));

        $this->exitSuccessfully();
    }

    /**
     * Exit is needed at the moment, as we have an EventDispatcher set on
     * the application, which means classes try to get loaded (such as
     * {@see \Symfony\Component\Console\Event\ConsoleTerminateEvent}), but
     * AFTER we've over-written the PHAR. This results in weird behaviour when
     * the class tries to get loaded, as the PHAR content changed. Not an
     * ideal approach, need to look into better ways of handling it, maybe
     * adding an event listener to overwrite the PHAR *after* the command runs.
     */
    private function exitSuccessfully(): never
    {
        exit(0);
    }
}
