<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Filter\TagFilter;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Php\PieBehaviourTest\CliContext;

$profile = (new Profile('default'))
    ->withSuite(
        (new Suite('default'))
            ->withContexts(CliContext::class)
            ->withPaths('%paths.base%/features')
            ->withFilter(new TagFilter('~@wip')),
    );

return (new Config())
    ->withProfile($profile);
