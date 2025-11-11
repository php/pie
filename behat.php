<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Filter\TagFilter;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Php\PieBehaviourTest\CliContext;

if (getenv('USING_PIE_BEHAT_DOCKERFILE') !== '1') {
    echo <<<'HELP'
⚠️ ⚠️ ⚠️  STOP! ⚠️ ⚠️ ⚠️

This test suite tinkers with your system, and has lots of expectations about
the system it is running on, so we HIGHLY recommend you run it using the
provided Dockerfile:

  docker buildx build --file .github/actions/pie-behaviour-tests/Dockerfile -t pie-behat-test .
  docker run --volume .:/github/workspace -ti pie-behat-test

If you are really sure, and accept that the test suite installs/uninstalls
stuff from your system, and might break your stuff, set
USING_PIE_BEHAT_DOCKERFILE=1 in your environment.

HELP;
    exit(1);
}

$profile = (new Profile('default'))
    ->withSuite(
        (new Suite('default'))
            ->withContexts(CliContext::class)
            ->withPaths('%paths.base%/features')
            ->withFilter(new TagFilter('~@wip')),
    );

return (new Config())
    ->withProfile($profile);
