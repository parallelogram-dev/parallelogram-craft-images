<?php

declare(strict_types=1);

namespace parallelogram\images\sizing;

use function array_unique;
use function array_values;

/**
 * The size sets to pre-generate for images in one asset field, either in every
 * field layout or only in one. Keyed by UID, so renaming a field or overriding
 * its handle in a layout doesn't break it.
 *
 * This only decides what is generated ahead of time. Templates can ask for any
 * size set, and anything missing is generated on first request.
 *
 * @api
 */
final readonly class FieldAssignment
{
    /**
     * @var list<string>
     */
    public array $sizeSets;

    /**
     * @param list<string> $sizeSets Size set handles.
     *
     * @throws InvalidSizeSetException When the field UID is empty or no size set is given.
     */
    public function __construct(
        public string $fieldUid,
        array $sizeSets,
        public ?string $layoutUid = null,
    ) {
        if ('' === $fieldUid) {
            throw new InvalidSizeSetException('A field assignment needs a field.');
        }

        if ([] === $sizeSets) {
            throw new InvalidSizeSetException('A field assignment needs at least one size set.');
        }

        $this->sizeSets = array_values(array_unique($sizeSets));
    }

    /**
     * Whether it covers the field in the given layout.
     */
    public function appliesTo(string $fieldUid, ?string $layoutUid): bool
    {
        return $fieldUid === $this->fieldUid && (null === $this->layoutUid || $layoutUid === $this->layoutUid);
    }
}
