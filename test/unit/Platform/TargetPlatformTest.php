<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform;

use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use Php\Pie\Platform\WindowsCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TargetPlatform::class)]
final class TargetPlatformTest extends TestCase
{
    public function testWindowsPlatformFromPhpInfo(): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('operatingSystem')
            ->willReturn(OperatingSystem::Windows);
        $phpBinaryPath->expects(self::any())
            ->method('operatingSystemFamily')
            ->willReturn(OperatingSystemFamily::Windows);
        $phpBinaryPath->expects(self::any())
            ->method('machineType')
            ->willReturn(Architecture::x86);
        $phpBinaryPath->expects(self::any())
            ->method('phpinfo')
            ->willReturn(<<<'TEXT'
phpinfo()
PHP Version => 8.3.6

System => Windows NT MYCOMPUTER 10.0 build 19045 (Windows 10) AMD64
Build Date => Apr 10 2024 14:51:55
Build System => Microsoft Windows Server 2019 Datacenter [10.0.17763]
Compiler => Visual C++ 2019
Architecture => x64
Configure Command => cscript /nologo /e:jscript configure.js  "--enable-snapshot-build" "--enable-debug-pack" "--with-pdo-oci=..\..\..\..\instantclient\sdk,shared" "--with-oci8-19=..\..\..\..\instantclient\sdk,shared" "--enable-object-out-dir=../obj/" "--enable-com-dotnet=shared" "--without-analyzer" "--with-pgo"
Server API => Command Line Interface
Virtual Directory Support => enabled
Configuration File (php.ini) Path =>
Loaded Configuration File => C:\php-8.3.6\php.ini
Scan this dir for additional .ini files => (none)
Additional .ini files parsed => (none)
PHP API => 20230831
PHP Extension => 20230831
Zend Extension => 420230831
Zend Extension Build => API420230831,TS,VS16
PHP Extension Build => API20230831,TS,VS16
TEXT);

        $platform = TargetPlatform::fromPhpBinaryPath($phpBinaryPath, null);

        self::assertSame(OperatingSystem::Windows, $platform->operatingSystem);
        self::assertSame(OperatingSystemFamily::Windows, $platform->operatingSystemFamily);
        self::assertSame(WindowsCompiler::VS16, $platform->windowsCompiler);
        self::assertSame(ThreadSafetyMode::ThreadSafe, $platform->threadSafety);
        self::assertSame(Architecture::x86_64, $platform->architecture);
    }

    public function testLinuxPlatform(): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('operatingSystem')
            ->willReturn(OperatingSystem::NonWindows);
        $phpBinaryPath->expects(self::any())
            ->method('operatingSystemFamily')
            ->willReturn(OperatingSystemFamily::Linux);
        $phpBinaryPath->expects(self::any())
            ->method('machineType')
            ->willReturn(Architecture::x86_64);
        $phpBinaryPath->expects(self::any())
            ->method('phpinfo')
            ->willReturn(<<<'TEXT'
phpinfo()
PHP Version => 8.3.6

System => Linux myhostname 1.2.3 Ubuntu x86_64
Build Date => Apr 11 2024 20:23:38
Build System => Linux
Server API => Command Line Interface
Virtual Directory Support => disabled
Configuration File (php.ini) Path => /etc/php/8.3/cli
Loaded Configuration File => /etc/php/8.3/cli/php.ini
Scan this dir for additional .ini files => /etc/php/8.3/cli/conf.d
Additional .ini files parsed => (none)
PHP API => 20230831
PHP Extension => 20230831
Zend Extension => 420230831
Zend Extension Build => API420230831,NTS
PHP Extension Build => API20230831,NTS
Debug Build => no
Thread Safety => disabled
TEXT);

        $platform = TargetPlatform::fromPhpBinaryPath($phpBinaryPath, null);

        self::assertSame(OperatingSystem::NonWindows, $platform->operatingSystem);
        self::assertSame(OperatingSystemFamily::Linux, $platform->operatingSystemFamily);
        self::assertNull($platform->windowsCompiler);
        self::assertSame(ThreadSafetyMode::NonThreadSafe, $platform->threadSafety);
        self::assertSame(Architecture::x86_64, $platform->architecture);
    }
}
