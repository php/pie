<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration;

use Php\Pie\ComposerIntegration\MinimalHelperSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\QuestionHelper;

#[CoversClass(MinimalHelperSet::class)]
class MinimalHelperSetTest extends TestCase
{
    public function testHappyPath(): void
    {
        $this->expectNotToPerformAssertions();
        new MinimalHelperSet(['question' => $this->createMock(QuestionHelper::class)]);
    }
}
