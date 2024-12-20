<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform\Git;

use Composer\Util\Platform;
use Php\Pie\Platform\Git\Exception\InvalidGitBinaryPath;
use Php\Pie\Platform\Git\GitBinaryPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GitBinaryPath::class)]
class GitBinaryPathTest extends TestCase
{
    private const FAKE_GIT_EXECUTABLE = __DIR__ . '/../../../assets/fake-git.sh';

    public function testNonExistentPhpBinaryIsRejected(): void
    {
        $this->expectException(InvalidGitBinaryPath::class);
        $this->expectExceptionMessage('does not exist');
        GitBinaryPath::fromGitBinaryPath(__DIR__ . '/path/to/a/non/existent/git/binary');
    }

    public function testNonExecutablePhpBinaryIsRejected(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('is_executable always returns false on Windows');
        }

        $this->expectException(InvalidGitBinaryPath::class);
        $this->expectExceptionMessage('is not executable');
        GitBinaryPath::fromGitBinaryPath(__FILE__);
    }

    public function testInvalidGitBinaryIsRejected(): void
    {
        $this->expectException(InvalidGitBinaryPath::class);
        $this->expectExceptionMessage('does not appear to be a git binary');
        GitBinaryPath::fromGitBinaryPath(self::FAKE_GIT_EXECUTABLE);
    }
}
