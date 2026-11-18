<?php

declare(strict_types=1);

namespace parallelogram\images\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\Html;
use Override;
use parallelogram\images\colour\HexColour;
use yii\db\Schema;

/**
 * Holds an image's most common colour, which `asset.picture()` shows behind
 * the image while it loads. Add it to a volume's field layout; the plugin
 * works the colour out whenever an image is uploaded or replaced, and
 * `php craft p-images/placeholders` fills it in for existing ones. Editors
 * see it but can't change it.
 *
 * @api
 */
final class PlaceholderColourField extends Field
{
    #[Override]
    public static function dbType(): string
    {
        return Schema::TYPE_CHAR . '(7)';
    }

    #[Override]
    public static function displayName(): string
    {
        return Craft::t('p-images', message: 'Placeholder Colour');
    }

    #[Override]
    public static function icon(): string
    {
        return 'palette';
    }

    #[Override]
    public static function phpType(): string
    {
        return 'string|null';
    }

    private static function swatch(mixed $value): string
    {
        $colour = HexColour::normalise($value);

        if (null === $colour) {
            return Html::tag(
                'p',
                Craft::t('p-images', message: 'Worked out when the image is uploaded.'),
                ['class' => 'light'],
            );
        }

        return (
            Html::tag(
                'div',
                Html::tag('div', content: '', options: [
                    'class' => 'color-preview',
                    'style' => ['background-color' => $colour],
                ]),
                ['class' => ['color', 'noteditable']],
            ) . Html::tag('div', $colour, ['class' => ['colorhex', 'code']])
        );
    }

    #[Override]
    public function getStaticHtml(mixed $value, ElementInterface $element): string
    {
        return self::swatch($value);
    }

    #[Override]
    public function normalizeValue(mixed $value, ?ElementInterface $element): ?string
    {
        return HexColour::normalise($value);
    }

    #[Override]
    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        return self::swatch($value);
    }
}
