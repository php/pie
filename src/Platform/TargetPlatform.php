<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

use Fidry\CpuCoreCounter\CpuCoreCounter;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPhp\PhpizePath;

use function explode;
use function function_exists;
use function posix_getuid;
use function Safe\preg_match;
use function trim;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
class TargetPlatform
{
    private static LibcFlavour $libcFlavour;

    public function __construct(
        public readonly OperatingSystem $operatingSystem,
        public readonly OperatingSystemFamily $operatingSystemFamily,
        public readonly PhpBinaryPath $phpBinaryPath,
        public readonly Architecture $architecture,
        public readonly ThreadSafetyMode $threadSafety,
        public readonly int $makeParallelJobs,
        public readonly WindowsCompiler|null $windowsCompiler,
        public readonly PhpizePath|null $phpizePath,
    ) {
    }

    public function libcFlavour(): LibcFlavour
    {
        if (! isset(self::$libcFlavour)) {
            self::$libcFlavour = LibcFlavour::detect();
        }

        return self::$libcFlavour;
    }

    public static function isRunningAsRoot(): bool
    {
        return function_exists('posix_getuid') && posix_getuid() === 0;
    }

    public static function fromPhpBinaryPath(PhpBinaryPath $phpBinaryPath, int|null $makeParallelJobs, PhpizePath|null $phpizePath): self
    {
        $os              = $phpBinaryPath->operatingSystem();
        $osFamily        = $phpBinaryPath->operatingSystemFamily();
        $phpinfo         = $phpBinaryPath->phpinfo();
        $architecture    = $phpBinaryPath->machineType();
        $windowsCompiler = null;
        $threadSafety    = ThreadSafetyMode::ThreadSafe;

        /**
         * Based on xdebug.org wizard, copyright Derick Rethans, used under MIT licence
         *
         * @link https://github.com/xdebug/xdebug.org/blob/aff649f2c3ca303ad471e6ed9dd29c0db16d3e22/src/XdebugVersion.php#L276-L299
         */
        if (preg_match('/PHP Extension Build([ =>\t]+)(API.*)/', $phpinfo, $m)) {
            $parts = explode(',', trim($m[2]));
            foreach ($parts as $part) {
                switch ($part) {
                    case 'NTS':
                        $threadSafety = ThreadSafetyMode::NonThreadSafe;
                        break;
                    case 'TS':
                        $threadSafety = ThreadSafetyMode::ThreadSafe;
                        break;
                    case 'VC6':
                        $windowsCompiler = WindowsCompiler::VC6;
                        break;
                    case 'VC8':
                        $windowsCompiler = WindowsCompiler::VC8;
                        break;
                    case 'VC9':
                        $windowsCompiler = WindowsCompiler::VC9;
                        break;
                    case 'VC11':
                        $windowsCompiler = WindowsCompiler::VC11;
                        break;
                    case 'VC14':
                        $windowsCompiler = WindowsCompiler::VC14;
                        break;
                    case 'VC15':
                        $windowsCompiler = WindowsCompiler::VC15;
                        break;
                    case 'VS16':
                        $windowsCompiler = WindowsCompiler::VS16;
                        break;
                    case 'VS17':
                        $windowsCompiler = WindowsCompiler::VS17;
                        break;
                }
            }
        }

        if ($makeParallelJobs === null) {
            $makeParallelJobs = (new CpuCoreCounter())->getAvailableForParallelisation()->availableCpus;
        }

        return new self(
            $os,
            $osFamily,
            $phpBinaryPath,
            $architecture,
            $threadSafety,
            $makeParallelJobs,
            $windowsCompiler,
            $phpizePath,
        );
    }
}
