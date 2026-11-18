<?php

declare(strict_types=1);

namespace parallelogram\images\test\Trait;

use Craft;
use craft\elements\Asset;
use craft\helpers\FileHelper;
use parallelogram\images\Plugin;
use Throwable;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\db\Transaction;

use function file_put_contents;
use function implode;
use function is_dir;
use function sys_get_temp_dir;
use function uniqid;

/**
 * For tests that run inside the booted sandbox site: each test runs in a
 * database transaction that's rolled back, gets the plugin's original
 * settings back, and leaves the test volume and variant folders empty.
 *
 * Call {@see setUpCraft()} from setUp() and {@see tearDownCraft()} from tearDown().
 *
 * @internal
 */
trait UsesCraftAppTrait
{
    private ?Transaction $craftTransaction = null;

    /**
     * @var array<array-key, mixed>
     */
    private array $originalSettings = [];

    /**
     * Points variants at the test filesystem with one size set, "card": 200px
     * AVIF.
     *
     * @param array<string, mixed> $overrides
     *
     * @throws Exception When the plugin isn't installed in the test site.
     */
    protected function configureVariants(array $overrides = []): void
    {
        $this->plugin()->setSettings(
            $overrides
                + [
                    'variantFilesystem' => 'testVariants',
                    'variantPrefix'     => 'asset/variant',
                    'sizeSets'          => ['card' => ['widths' => [200], 'sizes' => '100vw', 'formats' => ['avif']]],
                ],
        );
    }

    /**
     * Saves an image into the test volume.
     *
     * @throws Exception When the asset can't be saved.
     * @throws Throwable
     */
    protected function makeImageAsset(string $contents, string $filename = 'harbour.jpg'): Asset
    {
        $volume = Craft::$app->getVolumes()->getVolumeByHandle('testImages')
            ?? throw new Exception('The sandbox has no testImages volume.');
        $path = sys_get_temp_dir() . '/' . uniqid('p-images-', more_entropy: true);
        file_put_contents($path, $contents);

        $asset               = new Asset();
        $asset->tempFilePath = $path;
        $asset->setFilename($filename);
        $asset->newFolderId = null === $volume->id
            ? null
            : Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id)?->id;
        $asset->volumeId               = $volume->id;
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(Asset::SCENARIO_CREATE);

        if (! Craft::$app->getElements()->saveElement($asset)) {
            throw new Exception('The test asset could not be saved: ' . implode(' ', $asset->getFirstErrors()));
        }

        return $asset;
    }

    /**
     * @throws Exception When the plugin isn't installed in the test site.
     */
    protected function plugin(): Plugin
    {
        return Plugin::getInstance() ?? throw new Exception('Parallelogram Images is not installed in the test site.');
    }

    /**
     * @throws Exception
     */
    protected function setUpCraft(): void
    {
        $this->craftTransaction = Craft::$app->getDb()->beginTransaction();
        $this->originalSettings = $this->plugin()->getSettings()->toArray();
    }

    /**
     * @throws ErrorException
     * @throws Exception
     */
    protected function tearDownCraft(): void
    {
        $this->craftTransaction?->rollBack();
        $this->plugin()->setSettings($this->originalSettings);

        foreach (['@storage/test-images', '@storage/test-variants'] as $alias) {
            $folder = Craft::getAlias($alias);

            if (! is_dir($folder)) {
                continue;
            }

            FileHelper::clearDirectory($folder);
        }
    }
}
