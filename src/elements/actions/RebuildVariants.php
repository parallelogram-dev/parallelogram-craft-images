<?php

declare(strict_types=1);

namespace parallelogram\images\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\AssetQuery;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Queue;
use Override;
use parallelogram\images\jobs\RebuildJob;

/**
 * Assets index action: deletes and remakes the selected images' variants,
 * one queue job per image.
 *
 * @api
 */
final class RebuildVariants extends ElementAction
{
    #[Override]
    public function getConfirmationMessage(): ?string
    {
        return Craft::t('p-images', message: 'Delete and remake the variants of the selected images?');
    }

    #[Override]
    public function getTriggerLabel(): string
    {
        return Craft::t('p-images', message: 'Rebuild image variants');
    }

    #[Override]
    public function performAction(ElementQueryInterface $query): bool
    {
        if (! $query instanceof AssetQuery) {
            return false;
        }

        $count = 0;

        foreach ($query->kind('image')->ids() as $id) {
            Queue::push(new RebuildJob(['assetId' => (int) $id]));
            ++$count;
        }

        $this->setMessage(Craft::t('p-images', message: 'Rebuilding variants of {n} images.', params: ['n' => $count]));

        return true;
    }
}
