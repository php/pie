<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration;

use Php\Pie\ComposerIntegration\MinimalHelperSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Console\Helper\QuestionHelper;

#[CoversClass(MinimalHelperSet::class)]
class MinimalHelperSetTest extends TestCase
{
    public function testHappyPath(): void
    {
        $this->expectNotToPerformAssertions();
        new MinimalHelperSet(['question' => $this->createMock(QuestionHelper::class)]);
    }

    public function testQuestionOptionIsMissing(): void
    {
        $this->expectExceptionMessage('The question option must be an instance of Symfony\Component\Console\Helper\QuestionHelper, got NULL');
        new MinimalHelperSet([]);
    }

    public function testQuestionOptionIsNotAQuestionHelper(): void
    {
        $this->expectExceptionMessage('The question option must be an instance of Symfony\Component\Console\Helper\QuestionHelper, got stdClass');
        new MinimalHelperSet(['question' => new stdClass()]);
    }
}
