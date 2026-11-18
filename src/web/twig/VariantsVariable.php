<?php

namespace parallelogram\images\web\twig;

use parallelogram\images\models\VariantSet;
use parallelogram\images\Plugin;

/**
 * The `craft.pImages` Twig variable.
 */
class VariantsVariable
{
    /**
     * Resolve (asset|element, field, key) to a VariantSet. Throws on an unknown
     * or unassigned key — never falls back.
     */
    public function variantSet(mixed $source, string $field, ?string $key = null): VariantSet
    {
        return Plugin::getInstance()->resolver->variantSet($source, $field, $key);
    }
}
