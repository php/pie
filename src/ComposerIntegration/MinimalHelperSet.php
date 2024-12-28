<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Webmozart\Assert\Assert;

class MinimalHelperSet extends HelperSet
{
    public function __construct(array $helpers = [])
    {
        Assert::isInstanceOf(
            $helpers['question'] ?? null,
            QuestionHelper::class,
            'The question option must be an instance of %2$s, got %s'
        );

        parent::__construct($helpers);
    }
}
