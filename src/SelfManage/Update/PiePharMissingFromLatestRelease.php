<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Update;

use RuntimeException;

use function sprintf;

/** @deprecated This exception is no longer thrown */
class PiePharMissingFromLatestRelease extends RuntimeException
{
    /** @param non-empty-string $tagName */
    public static function fromRelease(string $tagName): self
    {
        return new self(sprintf(
            'PIE release %s does not have a pie.phar attached yet, try again in a few minutes.',
            $tagName,
        ));
    }
}
