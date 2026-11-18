<?php

namespace parallelogram\images\services;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use InvalidArgumentException;
use parallelogram\images\models\VariantSet;
use parallelogram\images\Plugin;
use yii\base\Component;

/**
 * Strictly resolves (asset|element, field, key) to a VariantSet. Throws — never
 * falls back — when the field is unassigned, the key is undefined, the key is
 * not assigned to the field, or a multi-matrix field is called without a key.
 */
class Resolver extends Component
{
    public function variantSet(mixed $source, string $field, ?string $key = null): VariantSet
    {
        $settings = Plugin::getInstance()->getSettings();

        $assigned = $settings->fieldAssignments[$field] ?? [];
        if ([] === $assigned) {
            throw new InvalidArgumentException("Field “{$field}” has no variant matrix assigned.");
        }

        if (null === $key) {
            if (count($assigned) > 1) {
                throw new InvalidArgumentException(
                    "Field “{$field}” allows multiple matrices (" . implode(', ', $assigned) . '); specify one.',
                );
            }
            $key = $assigned[0];
        } elseif (! in_array($key, $assigned, true)) {
            throw new InvalidArgumentException("Matrix “{$key}” is not assigned to field “{$field}”.");
        }

        $matrix = $settings->matrices[$key] ?? null;
        if (null === $matrix) {
            throw new InvalidArgumentException("Matrix “{$key}” does not exist.");
        }

        $asset = $this->resolveAsset($source, $field);
        if (null === $asset) {
            throw new InvalidArgumentException("No asset found for field “{$field}”.");
        }

        $formats = $matrix['formats'] ?? $settings->defaults['formats'] ?? [];
        if (! Craft::$app->getImages()->getIsImagick()) {
            $formats = array_values(array_filter($formats, static fn($format) => 'avif' !== $format));
        }

        return new VariantSet(
            $asset,
            $matrix,
            $settings->formatQuality,
            $settings->defaults,
            array_values($formats),
            Plugin::getInstance()->paths,
        );
    }

    private function resolveAsset(mixed $source, string $field): ?Asset
    {
        if ($source instanceof Asset) {
            return $source;
        }

        if ($source instanceof ElementInterface) {
            $value = $source->getFieldValue($field);
            if ($value instanceof AssetQuery) {
                return $value->one();
            }
        }

        return null;
    }
}
