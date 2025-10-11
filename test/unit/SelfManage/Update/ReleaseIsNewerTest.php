<?php

declare(strict_types=1);

namespace Php\PieUnitTest\SelfManage\Update;

use Php\Pie\SelfManage\Update\Channel;
use Php\Pie\SelfManage\Update\ReleaseIsNewer;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReleaseIsNewer::class)]
final class ReleaseIsNewerTest extends TestCase
{
    /** @return array<non-empty-string, array{0: Channel, 1: non-empty-string, 2: non-empty-string, 3: bool}> */
    public function provider(): array
    {
        return [
            'stable-oldstable-to-newstable'    => [Channel::Stable, '1.0.0', '1.0.1', true],
            'stable-newstable-to-oldstable'    => [Channel::Stable, '1.0.1', '1.0.0', false],
            'stable-oldstable-to-newpreview'   => [Channel::Stable, '1.0.0', '1.0.1-rc1', false],
            'stable-newstable-to-newpreview'   => [Channel::Stable, '1.0.1', '1.0.1-rc1', false],
            'stable-stable-to-nightly'         => [Channel::Stable, '1.0.0', 'dev-main', false],
            'stable-oldpreview-to-newpreview'  => [Channel::Stable, '1.0.1-rc1', '1.0.1-rc2', false],
            'stable-newpreview-to-oldpreview'  => [Channel::Stable, '1.0.1-rc2', '1.0.1-rc1', false],
            'stable-preview-to-oldstable'      => [Channel::Stable, '1.0.1-rc1', '1.0.0', true],
            'stable-preview-to-newstable'      => [Channel::Stable, '1.0.1-rc1', '1.0.1', true],
            'stable-preview-to-nightly'        => [Channel::Stable, '1.0.1-rc1', 'dev-main', false],
            'stable-nightly-to-nightly'        => [Channel::Stable, 'dev-main', 'dev-main', false],
            'stable-nightly-to-stable'         => [Channel::Stable, 'dev-main', '1.0.0', true],
            'stable-nightly-to-preview'        => [Channel::Stable, 'dev-main', '1.0.1-rc1', false],

            'preview-oldstable-to-newstable'   => [Channel::Preview, '1.0.0', '1.0.1', true],
            'preview-newstable-to-oldstable'   => [Channel::Preview, '1.0.1', '1.0.0', false],
            'preview-oldstable-to-newpreview'  => [Channel::Preview, '1.0.0', '1.0.1-rc1', true],
            'preview-newstable-to-newpreview'  => [Channel::Preview, '1.0.1', '1.0.1-rc1', false],
            'preview-stable-to-nightly'        => [Channel::Preview, '1.0.0', 'dev-main', false],
            'preview-oldpreview-to-newpreview' => [Channel::Preview, '1.0.1-rc1', '1.0.1-rc2', true],
            'preview-newpreview-to-oldpreview' => [Channel::Preview, '1.0.1-rc2', '1.0.1-rc1', false],
            'preview-preview-to-oldstable'     => [Channel::Preview, '1.0.1-rc1', '1.0.0', false],
            'preview-preview-to-newstable'     => [Channel::Preview, '1.0.1-rc1', '1.0.1', true],
            'preview-preview-to-nightly'       => [Channel::Preview, '1.0.1-rc1', 'dev-main', false],
            'preview-nightly-to-nightly'       => [Channel::Preview, 'dev-main', 'dev-main', false],
            'preview-nightly-to-stable'        => [Channel::Preview, 'dev-main', '1.0.0', true],
            'preview-nightly-to-preview'       => [Channel::Preview, 'dev-main', '1.0.1-rc1', true],

            'nightly-oldstable-to-newstable'   => [Channel::Nightly, '1.0.0', '1.0.1', true],
            'nightly-newstable-to-oldstable'   => [Channel::Nightly, '1.0.1', '1.0.0', false],
            'nightly-oldstable-to-newpreview'  => [Channel::Nightly, '1.0.0', '1.0.1-rc1', true],
            'nightly-newstable-to-newpreview'  => [Channel::Nightly, '1.0.1', '1.0.1-rc1', false],
            'nightly-stable-to-nightly'        => [Channel::Nightly, '1.0.0', 'dev-main', true],
            'nightly-oldpreview-to-newpreview' => [Channel::Nightly, '1.0.1-rc1', '1.0.1-rc2', true],
            'nightly-newpreview-to-oldpreview' => [Channel::Nightly, '1.0.1-rc2', '1.0.1-rc1', false],
            'nightly-preview-to-oldstable'     => [Channel::Nightly, '1.0.1-rc1', '1.0.0', false],
            'nightly-preview-to-newstable'     => [Channel::Nightly, '1.0.1-rc1', '1.0.1', true],
            'nightly-preview-to-nightly'       => [Channel::Nightly, '1.0.1-rc1', 'dev-main', true],
            'nightly-nightly-to-nightly'       => [Channel::Nightly, 'dev-main', 'dev-main', true],
            'nightly-nightly-to-stable'        => [Channel::Nightly, 'dev-main', '1.0.0', false],
            'nightly-nightly-to-preview'       => [Channel::Nightly, 'dev-main', '1.0.1-rc1', false],
        ];
    }

    /**
     * @param non-empty-string $currentPieVersion
     * @param non-empty-string $newReleaseTag
     */
    #[DataProvider('provider')]
    public function testReleaseIsNewerForChannel(
        Channel $updateChannel,
        string $currentPieVersion,
        string $newReleaseTag,
        bool $shouldUpgrade,
    ): void {
        self::assertSame(
            $shouldUpgrade,
            ReleaseIsNewer::forChannel(
                $updateChannel,
                $currentPieVersion,
                new ReleaseMetadata($newReleaseTag, 'ignored'),
            ),
        );
    }
}
