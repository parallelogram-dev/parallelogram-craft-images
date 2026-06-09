<?php

namespace parallelogram\imagevariants\models;

use craft\base\Model;

/**
 * Plugin settings: the variant-matrix catalogue, per-format encoder quality, and
 * the field→matrix assignments. Persisted to Project Config and overridable by
 * config/image-variants.php.
 *
 * The canonical store is {@see $matrices} (a handle-keyed map). The CP edits it
 * through the flat {@see $matrixRows} representation (one row per matrix, formats
 * as ticks); the two are converted on display and on save. Encoder quality is
 * per format and global ({@see $formatQuality}) — AVIF/WebP reach comparable
 * quality at far lower numbers than JPG, so a single shared value is meaningless.
 */
class Settings extends Model
{
    public const ALLOWED_FORMATS = ['avif', 'webp', 'jpg', 'png'];
    public const ALLOWED_MODES = ['crop', 'fit', 'stretch', 'letterbox'];

    /**
     * Cascading defaults applied to any matrix that does not override them.
     *
     * @var array<string, mixed>
     */
    public array $defaults = [
        'formats' => ['avif', 'webp', 'jpg'],
        'mode' => 'crop',
        'fallbackFormat' => 'jpg',
        'fallbackMime' => 'image/jpeg',
    ];

    /**
     * Encoder quality per format (1–100), applied globally.
     *
     * @var array<string, int>
     */
    public array $formatQuality = [
        'avif' => 60,
        'webp' => 80,
        'jpg' => 82,
        'png' => 80,
    ];

    /**
     * Variant matrices, keyed by handle. Each: sizes, variants, formats. A
     * variant requires at least one of width/height; omitting one scales that
     * axis proportionally.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $matrices = [];

    /**
     * Asset field handle => list of matrix handles the field may render with.
     *
     * @var array<string, string[]>
     */
    public array $fieldAssignments = [];

    /**
     * Transient editableTable payload posted by the CP; converted into
     * {@see $matrices} in {@see beforeValidate()} and never persisted.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $matrixRows = [];

    /**
     * Transient field-assignment payload posted by the CP; converted into
     * {@see $fieldAssignments} in {@see beforeValidate()} and never persisted.
     * The `__submitted` marker lets a fully-cleared form replace the map.
     *
     * @var array<string, mixed>
     */
    public array $fieldAssignmentInput = [];

    public function init(): void
    {
        parent::init();

        if ($this->matrices === []) {
            $this->matrices = self::seedMatrices();
        }

        if ($this->fieldAssignments === []) {
            $this->fieldAssignments = self::seedFieldAssignments();
        }
    }

    public function rules(): array
    {
        return [
            [['defaults', 'matrices', 'fieldAssignments', 'matrixRows', 'fieldAssignmentInput', 'formatQuality'], 'safe'],
            ['matrices', 'validateMatrices'],
            ['fieldAssignments', 'validateAssignments'],
            ['formatQuality', 'validateFormatQuality'],
        ];
    }

    /**
     * Keep the transient editableTable payload out of Project Config.
     */
    public function fields(): array
    {
        $fields = parent::fields();
        unset($fields['matrixRows'], $fields['fieldAssignmentInput']);

        return $fields;
    }

    public function beforeValidate(): bool
    {
        if ($this->matrixRows !== []) {
            $this->matrices = self::matricesFromRows($this->matrixRows);
        }

        if (isset($this->fieldAssignmentInput['__submitted'])) {
            $this->fieldAssignments = self::assignmentsFromInput($this->fieldAssignmentInput);
        }

        $this->formatQuality = array_map(static fn($q) => (int)$q, $this->formatQuality);

        return parent::beforeValidate();
    }

    /**
     * Validate the converted matrices. Errors are attached to `matrices`.
     */
    public function validateMatrices(): void
    {
        if ($this->matrixRows !== []) {
            $handles = array_filter(array_map(
                static fn($row) => trim((string)($row['handle'] ?? '')),
                $this->matrixRows
            ));

            if (count($handles) !== count(array_unique($handles))) {
                $this->addError('matrices', 'Matrix handles must be unique.');
            }
        }

        foreach ($this->matrices as $handle => $matrix) {
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', (string)$handle)) {
                $this->addError('matrices', "Invalid matrix handle “{$handle}”.");
            }

            if (trim((string)($matrix['sizes'] ?? '')) === '') {
                $this->addError('matrices', "Matrix “{$handle}” needs a sizes value.");
            }

            if (empty($matrix['variants'])) {
                $this->addError('matrices', "Matrix “{$handle}” needs at least one width.");
            }

            $formats = $matrix['formats'] ?? [];
            if ($formats === []) {
                $this->addError('matrices', "Matrix “{$handle}” needs at least one format ticked.");
            }

            foreach ($formats as $format) {
                if (!in_array($format, self::ALLOWED_FORMATS, true)) {
                    $this->addError('matrices', "Matrix “{$handle}” has an unknown format “{$format}”.");
                }
            }

            $mode = $matrix['mode'] ?? $this->defaults['mode'];
            if (!in_array($mode, self::ALLOWED_MODES, true)) {
                $this->addError('matrices', "Matrix “{$handle}” has an unknown mode “{$mode}”.");
            }
        }
    }

    /**
     * Validate per-format quality values.
     */
    public function validateFormatQuality(): void
    {
        foreach ($this->formatQuality as $format => $quality) {
            if (!in_array($format, self::ALLOWED_FORMATS, true)) {
                $this->addError('formatQuality', "Unknown format “{$format}”.");
                continue;
            }

            if (!is_int($quality) || $quality < 1 || $quality > 100) {
                $this->addError('formatQuality', ucfirst($format) . ' quality must be between 1 and 100.');
            }
        }
    }

    /**
     * Every assigned matrix handle must exist.
     */
    public function validateAssignments(): void
    {
        foreach ($this->fieldAssignments as $field => $handles) {
            foreach ((array)$handles as $handle) {
                if (!isset($this->matrices[$handle])) {
                    $this->addError('fieldAssignments', "Field “{$field}” references unknown matrix “{$handle}”.");
                }
            }
        }
    }

    /**
     * Rebuild the canonical field→matrices map from the posted CP payload.
     * Fields with nothing ticked are dropped.
     *
     * @param array<string, mixed> $input
     * @return array<string, string[]>
     */
    public static function assignmentsFromInput(array $input): array
    {
        unset($input['__submitted']);

        $assignments = [];

        foreach ($input as $field => $handles) {
            $handles = array_values(array_filter(
                array_map('strval', (array)$handles),
                static fn($handle) => $handle !== ''
            ));

            if ($handles !== []) {
                $assignments[$field] = $handles;
            }
        }

        return $assignments;
    }

    /**
     * Convert posted editableTable rows into the canonical matrices map.
     * Phase 2 edits width-based variants; richer per-variant height/mode stays
     * available through the config file.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array<string, mixed>>
     */
    public static function matricesFromRows(array $rows): array
    {
        $matrices = [];

        foreach ($rows as $row) {
            $handle = trim((string)($row['handle'] ?? ''));
            if ($handle === '') {
                continue;
            }

            $widths = array_values(array_filter(
                array_map(static fn($w) => (int)trim((string)$w), explode(',', (string)($row['widths'] ?? ''))),
                static fn($w) => $w > 0
            ));

            $formats = array_values(array_filter(
                self::ALLOWED_FORMATS,
                static fn($format) => !empty($row[$format])
            ));

            $matrix = [
                'sizes' => trim((string)($row['sizes'] ?? '')),
                'variants' => array_map(static fn($w) => ['width' => $w], $widths),
                'formats' => $formats,
            ];

            if (!empty($row['mode'])) {
                $matrix['mode'] = (string)$row['mode'];
            }

            $matrices[$handle] = $matrix;
        }

        return $matrices;
    }

    /**
     * Flatten the canonical matrices into editableTable rows for display.
     *
     * @return array<int, array<string, mixed>>
     */
    public function matricesToRows(): array
    {
        $rows = [];

        foreach ($this->matrices as $handle => $matrix) {
            $enabled = $matrix['formats'] ?? $this->defaults['formats'] ?? [];

            $row = [
                'handle' => $handle,
                'sizes' => $matrix['sizes'] ?? '',
                'widths' => implode(',', array_map(
                    static fn($variant) => $variant['width'] ?? '',
                    $matrix['variants'] ?? []
                )),
                'mode' => $matrix['mode'] ?? $this->defaults['mode'] ?? 'crop',
            ];

            foreach (self::ALLOWED_FORMATS as $format) {
                $row[$format] = in_array($format, $enabled, true);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Rows for the editableTable value: the submitted payload when redisplaying
     * after a validation error, otherwise the stored matrices.
     *
     * @return array<int, array<string, mixed>>
     */
    public function matrixRowsForDisplay(): array
    {
        return $this->matrixRows !== [] ? $this->matrixRows : $this->matricesToRows();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function seedMatrices(): array
    {
        $formats = ['avif', 'webp', 'jpg'];

        $widths = static fn(array $px) => array_map(static fn($w) => ['width' => $w], $px);

        return [
            'full' => ['sizes' => '100vw', 'formats' => $formats, 'variants' => $widths([640, 960, 1280, 1600, 1920, 2560])],
            'contained' => ['sizes' => '(min-width: 908px) 900px, 100vw', 'formats' => $formats, 'variants' => $widths([480, 720, 960, 1280, 1600, 1920])],
            'card' => ['sizes' => '(min-width: 1350px) 25vw, (min-width: 908px) 33vw, 50vw', 'formats' => $formats, 'variants' => $widths([260, 360, 480, 640, 820, 960])],
            'tile' => ['sizes' => '(min-width: 908px) 50vw, 100vw', 'formats' => $formats, 'variants' => $widths([480, 680, 900, 1200, 1600])],
            'compact' => ['sizes' => '(min-width: 1350px) 16vw, (min-width: 908px) 25vw, 50vw', 'formats' => $formats, 'variants' => $widths([180, 260, 360, 480, 640])],
            'swatch' => ['sizes' => '6rem', 'formats' => $formats, 'variants' => $widths([96, 160, 224])],
        ];
    }

    /**
     * @return array<string, string[]>
     */
    public static function seedFieldAssignments(): array
    {
        return [
            'productImage' => ['full'],
            'material' => ['swatch'],
            'preview' => ['card', 'compact', 'tile', 'swatch'],
            'image' => ['compact', 'full', 'contained'],
            'articleImage' => ['full', 'swatch'],
            'articlePreview' => ['compact'],
        ];
    }
}
