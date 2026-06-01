<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

use function file_exists;
use function Safe\putenv;
use function sys_get_temp_dir;
use function uniqid;

class IsolatedWorkingDirectoryTestCase extends TestCase
{
    private string $tempPieDir;

    public function setUp(): void
    {
        $this->tempPieDir = sys_get_temp_dir() . '/pie-test-' . uniqid();
        putenv('PIE_WORKING_DIRECTORY=' . $this->tempPieDir);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempPieDir)) {
            (new Process(['rm', '-rf', $this->tempPieDir]))->run();
        }

        putenv('PIE_WORKING_DIRECTORY');
    }
}
