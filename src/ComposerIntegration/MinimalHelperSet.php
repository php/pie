<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Webmozart\Assert\Assert;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class MinimalHelperSet extends HelperSet
{
    /** @param array{question?: QuestionHelper|mixed} $helpers */
    public function __construct(array $helpers)
    {
        Assert::isInstanceOf(
            $helpers['question'] ?? null,
            QuestionHelper::class,
            'The question option must be an instance of %2$s, got %s',
        );

        parent::__construct($helpers);
    }
}
