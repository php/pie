<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Verify;

use RuntimeException;

use function sprintf;

class GithubCliNotAvailable extends RuntimeException
{
    public static function fromExpectedGhToolName(string $expectedGhToolName): self
    {
        return new self(sprintf('The GitHub "%s" CLI tool was not available.', $expectedGhToolName));
    }

    public static function withMissingAttestationCommand(string $expectedGhToolName): self
    {
        return new self(sprintf('The GitHub "%s" CLI tool was available, but the `gh attestation` command failed; perhaps this version is out of date.', $expectedGhToolName));
    }
}
