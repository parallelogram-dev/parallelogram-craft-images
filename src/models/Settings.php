<?php

namespace parallelogram\imagevariants\models;

use craft\base\Model;

/**
 * Plugin settings: the variant-matrix catalogue and the field→matrix
 * assignments. Persisted to Project Config and overridable by
 * config/image-variants.php.
 *
 * Seed values mirror the previous config/imageprofiles.php so behaviour is
 * unchanged on first install. Full CRUD + validation land in later phases.
 */
class Settings extends Model
{
    /**
     * Cascading defaults applied to any matrix that does not override them.
     *
     * @var array<string, mixed>
     */
    public array $defaults = [
        'formats' => ['avif', 'webp', 'jpg'],
        'quality' => 80,
        'avifQuality' => 60,
        'mode' => 'crop',
        'fallbackFormat' => 'jpg',
        'fallbackMime' => 'image/jpeg',
    ];

    /**
     * Variant matrices, keyed by handle. Each: sizes, variants, and optional
     * formats/quality/mode overrides. A variant requires at least one of
     * width/height; omitting one scales that axis proportionally.
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

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function seedMatrices(): array
    {
        return [
            'full' => [
                'sizes' => '100vw',
                'variants' => [
                    ['width' => 640], ['width' => 960], ['width' => 1280],
                    ['width' => 1600], ['width' => 1920], ['width' => 2560],
                ],
            ],
            'contained' => [
                'sizes' => '(min-width: 908px) 900px, 100vw',
                'variants' => [
                    ['width' => 480], ['width' => 720], ['width' => 960],
                    ['width' => 1280], ['width' => 1600], ['width' => 1920],
                ],
            ],
            'card' => [
                'sizes' => '(min-width: 1350px) 25vw, (min-width: 908px) 33vw, 50vw',
                'variants' => [
                    ['width' => 260], ['width' => 360], ['width' => 480],
                    ['width' => 640], ['width' => 820], ['width' => 960],
                ],
            ],
            'tile' => [
                'sizes' => '(min-width: 908px) 50vw, 100vw',
                'variants' => [
                    ['width' => 480], ['width' => 680], ['width' => 900],
                    ['width' => 1200], ['width' => 1600],
                ],
            ],
            'compact' => [
                'sizes' => '(min-width: 1350px) 16vw, (min-width: 908px) 25vw, 50vw',
                'variants' => [
                    ['width' => 180], ['width' => 260], ['width' => 360],
                    ['width' => 480], ['width' => 640],
                ],
            ],
            'swatch' => [
                'sizes' => '6rem',
                'variants' => [
                    ['width' => 96], ['width' => 160], ['width' => 224],
                ],
            ],
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
