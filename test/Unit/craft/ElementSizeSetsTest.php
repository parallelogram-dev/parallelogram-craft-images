<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\ElementCollection;
use craft\fields\Assets;
use craft\fields\PlainText;
use craft\models\FieldLayout;
use parallelogram\images\craft\ElementSizeSets;
use parallelogram\images\craft\FieldAssets;
use parallelogram\images\craft\WarmRequest;
use parallelogram\images\sizing\FieldAssignment;
use parallelogram\images\sizing\FieldAssignments;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function array_keys;

/**
 * @internal
 */
#[CoversClass(ElementSizeSets::class)]
#[CoversClass(WarmRequest::class)]
#[UsesClass(FieldAssets::class)]
#[UsesClass(FieldAssignment::class)]
#[UsesClass(FieldAssignments::class)]
#[Group('unit')]
final class ElementSizeSetsTest extends TestCase
{
    private const string LAYOUT = 'layout-1';

    #[Test]
    public function asksForEveryImageInAssignedFieldsOfANewElement(): void
    {
        $element = $this->element(['gallery' => [1, 2], 'hero' => [2, 3]]);
        $sets    = new ElementSizeSets(new FieldAssignments([
            new FieldAssignment('uid-gallery', ['card']),
            new FieldAssignment('uid-hero', ['wide', 'card']),
        ]));

        static::assertEquals(
            [new WarmRequest(1, ['card']), new WarmRequest(2, ['card', 'wide']), new WarmRequest(3, ['wide', 'card'])],
            $sets->requests($element, changed: null),
        );
    }

    #[Test]
    public function findsNothingInAnElementWithoutAFieldLayout(): void
    {
        $element = $this->createStub(ElementInterface::class);

        static::assertSame([], (new ElementSizeSets(new FieldAssignments()))->requests($element, changed: null));
    }

    #[Test]
    public function onlyLooksAtFieldsThatChanged(): void
    {
        $element = $this->element(['gallery' => [1], 'hero' => [2]]);
        $sets    = new ElementSizeSets(new FieldAssignments([
            new FieldAssignment('uid-gallery', ['card']),
            new FieldAssignment('uid-hero', ['wide']),
        ]));

        static::assertEquals([new WarmRequest(2, ['wide'])], $sets->requests($element, changed: ['hero']));
    }

    #[Test]
    public function skipsFieldsWithoutAnAssignmentOrOfAnotherType(): void
    {
        $element = $this->elementWithFields(['gallery' => [1]], ['text']);
        $sets    = new ElementSizeSets(new FieldAssignments([
            new FieldAssignment('uid-text', ['card']),
            new FieldAssignment('uid-other-layout', ['card'], layoutUid: 'someone-elses'),
        ]));

        static::assertSame([], $sets->requests($element, changed: null));
    }

    private function asset(int $id): Asset
    {
        $asset       = $this->createStub(Asset::class);
        $asset->id   = $id;
        $asset->kind = Asset::KIND_IMAGE;

        return $asset;
    }

    /**
     * @param array<string, list<int>> $galleries Asset IDs by field handle.
     */
    private function element(array $galleries): ElementInterface
    {
        return $this->elementWithFields($galleries, []);
    }

    /**
     * @param array<string, list<int>> $galleries Asset IDs by Assets field handle.
     * @param list<string> $textHandles Handles of plain text fields to include.
     */
    private function elementWithFields(array $galleries, array $textHandles): ElementInterface
    {
        $fields = [];

        foreach (array_keys($galleries) as $handle) {
            $field         = $this->createStub(Assets::class);
            $field->uid    = "uid-{$handle}";
            $field->handle = $handle;
            $fields[]      = $field;
        }

        foreach ($textHandles as $handle) {
            $text         = $this->createStub(PlainText::class);
            $text->uid    = "uid-{$handle}";
            $text->handle = $handle;
            $fields[]     = $text;
        }

        $layout      = $this->createStub(FieldLayout::class);
        $layout->uid = self::LAYOUT;
        $layout->method('getCustomFields')->willReturn($fields);

        $element = $this->createStub(ElementInterface::class);
        $element->method('getFieldLayout')->willReturn($layout);
        $element->method('getFieldValue')->willReturnMap($this->valueMap($galleries));

        return $element;
    }

    /**
     * @param array<string, list<int>> $galleries
     *
     * @return list<array{string, mixed}>
     */
    private function valueMap(array $galleries): array
    {
        $map = [];

        foreach ($galleries as $handle => $ids) {
            $assets = [];

            foreach ($ids as $id) {
                $assets[] = $this->asset($id);
            }

            $map[] = [$handle, new ElementCollection($assets)];
        }

        return $map;
    }
}
