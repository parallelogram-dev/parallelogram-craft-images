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
     * ImageMagick threads each encode may use (OpenMP). 0 leaves ImageMagick's
     * own default; higher speeds AVIF on multi-core servers, in one process.
     */
    public int $threadLimit = 0;

    /**
     * Use the in-process Imagick pipeline (decode once, resize each output from
     * the full-res source) instead of one Craft transform call per output. Much
     * faster on warming; falls back to Craft per-output for any geometry it does
     * not handle.
     */
    public bool $fastGenerate = true;

    /**
     * Convert each source to the sRGB colour profile before resizing, so
     * wide-gamut originals render predictably on the web. Applied once per asset.
     */
    public bool $convertToSrgb = true;

    /**
     * Unsharp-mask amount applied once per resized geometry (0 disables). A
     * gentle value recovers the softness a downscale introduces without haloing.
     */
    public float $sharpenAmount = 0.5;

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
            ['threadLimit', 'integer', 'min' => 0, 'max' => 64],
            [['fastGenerate', 'convertToSrgb'], 'boolean'],
            ['sharpenAmount', 'number', 'min' => 0, 'max' => 5],
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

    /**
     * @param array<int, string> $fields
     * @param array<int, string> $expand
     * @return array<string, mixed>
     */
    public function toArray(array $fields = [], array $expand = [], $recursive = true): array
    {
        if ($fields !== []) {
            $fields = array_merge(
                array_diff($fields, ['matrixRows', 'fieldAssignmentInput']),
                ['matrices', 'fieldAssignments'],
            );
        }

        return parent::toArray($fields, $expand, $recursive);
    }

    public function beforeValidate(): bool
    {
        if ($this->matrixRows !== []) {
            $this->matrices = self::matricesFromRows($this->matrixRows);
        }

        if (isset($this->fieldAssignmentInput['__submitted'])) {
            $this->fieldAssignments = self::assignmentsFromInput($this->fieldAssignmentInput);
        }

        $this->fieldAssignments = self::pruneAssignments($this->fieldAssignments, $this->matrices);

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
     * Drop assigned handles whose matrix no longer exists, and any field left
     * with none, so deleting a matrix cleans up its references instead of
     * blocking the save.
     *
     * @param array<string, string[]> $assignments
     * @param array<string, array<string, mixed>> $matrices
     * @return array<string, string[]>
     */
    private static function pruneAssignments(array $assignments, array $matrices): array
    {
        $pruned = [];

        foreach ($assignments as $field => $handles) {
            $valid = array_values(array_filter(
                $handles,
                static fn(string $handle) => isset($matrices[$handle])
            ));

            if ($valid !== []) {
                $pruned[$field] = $valid;
            }
        }

        return $pruned;
    }

    /**
     * Convert posted editableTable rows into the canonical matrices map. Each
     * variant may be a width, a width×height pair (cropped to the matrix mode),
     * or a height-only value — see {@see parseDimensions()}.
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

            $formats = array_values(array_filter(
                self::ALLOWED_FORMATS,
                static fn($format) => !empty($row[$format])
            ));

            $matrix = [
                'sizes' => trim((string)($row['sizes'] ?? '')),
                'variants' => self::parseDimensions((string)($row['dimensions'] ?? '')),
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
     * Parse a comma-separated dimensions string into variant definitions. Each
     * token is a width, a width×height pair, or a height-only value:
     *   640      → width 640 (height scales proportionally)
     *   800x600  → width 800, height 600 (cropped to the matrix mode)
     *   x600     → height 600 (width scales proportionally)
     * Either `x` or `×` separates the pair. Empty/invalid tokens are skipped.
     *
     * @return array<int, array<string, int>>
     */
    public static function parseDimensions(string $value): array
    {
        $variants = [];

        foreach (explode(',', $value) as $token) {
            $token = strtolower(trim(str_replace('×', 'x', $token)));
            if ($token === '') {
                continue;
            }

            [$w, $h] = array_pad(explode('x', $token, 2), 2, '');
            $width = (int)trim($w);
            $height = (int)trim($h);

            $variant = [];
            if ($width > 0) {
                $variant['width'] = $width;
            }
            if ($height > 0) {
                $variant['height'] = $height;
            }

            if ($variant !== []) {
                $variants[] = $variant;
            }
        }

        return $variants;
    }

    /**
     * Render one variant back to its compact token (640, 800x600, or x600).
     *
     * @param array<string, int> $variant
     */
    private static function dimensionToken(array $variant): string
    {
        $width = $variant['width'] ?? null;
        $height = $variant['height'] ?? null;

        if ($width && $height) {
            return $width . 'x' . $height;
        }
        if ($width) {
            return (string)$width;
        }
        if ($height) {
            return 'x' . $height;
        }

        return '';
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
                'dimensions' => implode(', ', array_filter(array_map(
                    static fn($variant) => self::dimensionToken($variant),
                    $matrix['variants'] ?? []
                ))),
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
