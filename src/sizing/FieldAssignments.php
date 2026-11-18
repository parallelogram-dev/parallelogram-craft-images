<?php

declare(strict_types=1);

namespace parallelogram\images\sizing;

use function array_keys;

/**
 * Every field assignment for a site.
 *
 * @api
 */
final readonly class FieldAssignments
{
    /**
     * @param list<FieldAssignment> $assignments
     */
    public function __construct(
        public array $assignments = [],
    ) {}

    /**
     * Drops size sets the catalogue no longer has, and any assignment left with
     * none, so deleting a size set cleans up after itself.
     */
    public function prunedTo(SizeSetCatalogue $catalogue): self
    {
        $kept = [];

        foreach ($this->assignments as $assignment) {
            $sizeSets = [];

            foreach ($assignment->sizeSets as $handle) {
                if (! $catalogue->has($handle)) {
                    continue;
                }

                $sizeSets[] = $handle;
            }

            if ([] === $sizeSets) {
                continue;
            }

            $kept[] = new FieldAssignment($assignment->fieldUid, $sizeSets, $assignment->layoutUid);
        }

        return new self($kept);
    }

    /**
     * The size sets to pre-generate for a field in a layout: those assigned to
     * the field everywhere plus those assigned to it in that layout.
     *
     * @return list<string>
     */
    public function sizeSetsFor(string $fieldUid, ?string $layoutUid): array
    {
        $handles = [];

        foreach ($this->assignments as $assignment) {
            if (! $assignment->appliesTo($fieldUid, $layoutUid)) {
                continue;
            }

            foreach ($assignment->sizeSets as $handle) {
                $handles[$handle] = true;
            }
        }

        return array_keys($handles);
    }
}
