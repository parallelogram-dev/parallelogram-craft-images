<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use craft\base\ElementInterface;
use craft\fields\Assets;
use parallelogram\images\sizing\FieldAssignments;

use function array_keys;
use function array_push;
use function array_unique;
use function array_values;
use function in_array;

/**
 * The images in an element's assigned Assets fields, with the size sets each
 * needs.
 *
 * @internal
 */
final readonly class ElementSizeSets
{
    public function __construct(
        private FieldAssignments $assignments,
    ) {}

    /**
     * One request per image, with every size set assigned to the fields it's in.
     *
     * @param list<string>|null $changed Handles of the fields that changed, or null for every field (a new element).
     *
     * @return list<WarmRequest>
     */
    public function requests(ElementInterface $element, ?array $changed): array
    {
        $layout = $element->getFieldLayout();
        $wanted = [];

        foreach ($layout?->getCustomFields() ?? [] as $field) {
            $handles = $this->assignments->sizeSetsFor((string) $field->uid, $layout?->uid);

            if ([] === $handles || null === $field->handle || ! $field instanceof Assets) {
                continue;
            }

            if (null !== $changed && ! in_array($field->handle, $changed, strict: true)) {
                continue;
            }

            foreach (FieldAssets::idsIn($element, $field->handle) as $id) {
                $wanted[$id] ??= [];
                array_push($wanted[$id], ...$handles);
            }
        }

        $requests = [];

        foreach (array_keys($wanted) as $id) {
            $requests[] = new WarmRequest($id, array_values(array_unique($wanted[$id] ?? [])));
        }

        return $requests;
    }
}
