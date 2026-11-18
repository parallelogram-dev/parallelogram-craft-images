<?php

namespace parallelogram\images\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\Asset;
use craft\elements\db\ElementQueryInterface;
use parallelogram\images\Plugin;

/**
 * Assets index action: force-rebuild the selected images' variants. Runs
 * synchronously — best for a handful of suspect assets; use the CLI
 * p-images/rebuild --volume for bulk.
 */
class RebuildVariants extends ElementAction
{
    public function getConfirmationMessage(): ?string
    {
        return Craft::t('p-images', 'Delete and regenerate variants for the selected image(s)?');
    }

    public function getTriggerLabel(): string
    {
        return Craft::t('p-images', 'Rebuild image variants');
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        $warmer = Plugin::getInstance()->warmer;
        $count  = 0;

        foreach ($query->kind('image')->all() as $asset) {
            if ($asset instanceof Asset) {
                $warmer->rebuild($asset);
                $count++;
            }
        }

        $this->setMessage(Craft::t('p-images', 'Rebuilt variants for {n} image(s).', ['n' => $count]));

        return true;
    }
}
