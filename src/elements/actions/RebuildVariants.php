<?php

namespace parallelogram\imagevariants\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\Asset;
use craft\elements\db\ElementQueryInterface;
use parallelogram\imagevariants\Plugin;

/**
 * Assets index action: force-rebuild the selected images' variants. Runs
 * synchronously — best for a handful of suspect assets; use the CLI
 * image-variants/rebuild --volume for bulk.
 */
class RebuildVariants extends ElementAction
{
    public function getTriggerLabel(): string
    {
        return Craft::t('image-variants', 'Rebuild image variants');
    }

    public function getConfirmationMessage(): ?string
    {
        return Craft::t('image-variants', 'Delete and regenerate variants for the selected image(s)?');
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        $warmer = Plugin::getInstance()->warmer;
        $count = 0;

        foreach ($query->kind('image')->all() as $asset) {
            if ($asset instanceof Asset) {
                $warmer->rebuild($asset);
                $count++;
            }
        }

        $this->setMessage(Craft::t('image-variants', 'Rebuilt variants for {n} image(s).', ['n' => $count]));

        return true;
    }
}
