<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Closure;
use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\Transaction;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Symfony\Component\Console\Output\OutputInterface;

use function array_filter;
use function assert;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class RemoveUnrelatedInstallOperations
{
    public function __construct(
        private readonly PieComposerRequest $composerRequest,
    ) {
    }

    public static function selfRegister(
        Composer $composer,
        PieComposerRequest $composerRequest,
    ): void {
        $composer
            ->getEventDispatcher()
            ->addListener(
                InstallerEvents::PRE_OPERATIONS_EXEC,
                new self($composerRequest),
            );
    }

    /**
     * @psalm-suppress InternalProperty
     * @psalm-suppress InternalMethod
     */
    public function __invoke(InstallerEvent $installerEvent): void
    {
        $pieOutput = $this->composerRequest->pieOutput;

        $newOperations = array_filter(
            $installerEvent->getTransaction()?->getOperations() ?? [],
            function (OperationInterface $operation) use ($pieOutput): bool {
                if (! $operation instanceof InstallOperation) {
                    $pieOutput->writeln(
                        sprintf(
                            'Unexpected operation during installer: %s',
                            $operation::class,
                        ),
                        OutputInterface::VERBOSITY_VERY_VERBOSE,
                    );

                    return false;
                }

                $isRequestedPiePackage = $this->composerRequest->requestedPackage->package === $operation->getPackage()->getName();

                if (! $isRequestedPiePackage) {
                    $pieOutput->writeln(
                        sprintf(
                            'Filtering package %s from install operations, as it was not the requested package',
                            $operation->getPackage()->getName(),
                        ),
                        OutputInterface::VERBOSITY_VERY_VERBOSE,
                    );
                }

                return $isRequestedPiePackage;
            },
        );

        $overrideOperations = Closure::Bind(
            static function (Transaction $transaction) use ($newOperations): void {
                /** @psalm-suppress InaccessibleProperty */
                $transaction->operations = $newOperations;
            },
            null,
            Transaction::class,
        );
        assert($overrideOperations !== null);
        $overrideOperations($installerEvent->getTransaction());
    }
}
