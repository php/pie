<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Installer;
use RuntimeException;

use function max;
use function sprintf;

class ComposerRunFailed extends RuntimeException
{
    public static function fromExitCode(int $exitCode): self
    {
        return new self(
            sprintf(
                'PIE Composer run failed with error code %d%s',
                $exitCode,
                self::tryDetermineConstant($exitCode),
            ),
            max(1, $exitCode), // ensure we are always non-zero for exit codes
        );
    }

    private static function tryDetermineConstant(int $errorCode): string
    {
        switch ($errorCode) {
            case Installer::ERROR_GENERIC_FAILURE:
                return ' (ERROR_GENERIC_FAILURE)';

            case Installer::ERROR_NO_LOCK_FILE_FOR_PARTIAL_UPDATE:
                return ' (ERROR_NO_LOCK_FILE_FOR_PARTIAL_UPDATE)';

            case Installer::ERROR_LOCK_FILE_INVALID:
                return ' (ERROR_LOCK_FILE_INVALID)';

            case Installer::ERROR_DEPENDENCY_RESOLUTION_FAILED:
                return ' (ERROR_DEPENDENCY_RESOLUTION_FAILED)';

            case Installer::ERROR_AUDIT_FAILED:
                return ' (ERROR_AUDIT_FAILED)';

            case Installer::ERROR_TRANSPORT_EXCEPTION:
                return ' (ERROR_TRANSPORT_EXCEPTION)';

            default:
                return '';
        }
    }
}
