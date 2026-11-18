<?php

declare(strict_types=1);

namespace parallelogram\images\models;

use craft\base\Model;
use Override;
use parallelogram\images\config\AssignmentConfig;
use parallelogram\images\config\BackendConfig;
use parallelogram\images\config\ConfigValues;
use parallelogram\images\config\SizeSetConfig;
use parallelogram\images\config\SizeSetRows;
use parallelogram\images\sizing\FieldAssignments;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\InvalidSizeSetException;
use parallelogram\images\sizing\QualityTable;
use parallelogram\images\sizing\SizeSetCatalogue;

use function array_key_exists;
use function array_map;

/**
 * Plugin settings, saved to project config and overridable from
 * `config/p-images.php`.
 *
 * Size sets and field assignments are stored as plain arrays (see
 * {@see SizeSetConfig} and {@see AssignmentConfig} for their shape) and read
 * into value objects through {@see catalogue()} and {@see assignments()}.
 * {@see SettingsValidator} checks them before they are saved.
 *
 * @api
 */
final class Settings extends Model
{
    public bool $autoOrient = true;

    /**
     * Which backend makes variants, site-wide and per volume, and the remote
     * services' connection details. See {@see BackendConfig} for its shape.
     *
     * @var array<array-key, mixed>
     */
    public array $backends = [];

    public bool $convertToSrgb = true;

    /**
     * Formats a size set uses when it doesn't list its own.
     *
     * @var list<string>
     */
    public array $defaultFormats = ['avif', 'jpg'];

    /**
     * Posted by the CP's assignment checkboxes; converted in
     * {@see beforeValidate()} and never saved.
     *
     * @var array<array-key, mixed>
     */
    public array $fieldAssignmentInput = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $fieldAssignments = [];

    /**
     * Encoder quality per format for the whole site. Formats left out use
     * their defaults (AVIF 60, WebP 80, JPG 82, PNG 80).
     *
     * @var array<string, int>
     */
    public array $formatQuality = [];

    public float $sharpenAmount = 0.5;

    /**
     * Posted by the CP's size set table; converted in {@see beforeValidate()}
     * and never saved.
     *
     * @var array<array-key, mixed>
     */
    public array $sizeSetRows = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $sizeSets = [];

    /**
     * ImageMagick threads per encode (0 leaves ImageMagick's default).
     */
    public int $threadLimit = 0;

    /**
     * Handle of the Craft filesystem variants are written to. Deliberately
     * empty until chosen: no variants are made or served before then.
     */
    public string $variantFilesystem = '';

    /**
     * Folder for variants within that filesystem, e.g. `asset/variant`. May
     * reference an environment variable. Deliberately empty until chosen.
     */
    public string $variantPrefix = '';

    /**
     * The field assignments, without any size set that no longer exists.
     *
     * @throws InvalidSizeSetException When the stored assignments or size sets are invalid.
     */
    public function assignments(): FieldAssignments
    {
        return AssignmentConfig::fromArray($this->fieldAssignments)->prunedTo($this->catalogue());
    }

    /**
     * Converts posted CP input into stored settings before the rules run:
     * table rows into size sets, checkboxes into assignments, and quality
     * boxes into numbers.
     */
    #[Override]
    public function beforeValidate(): bool
    {
        if ([] !== $this->sizeSetRows) {
            $this->sizeSets = SizeSetRows::toConfigs($this->sizeSetRows, $this->sizeSets);
        }

        $this->formatQuality = ConfigValues::postedIntegers($this->formatQuality);

        if (array_key_exists('__submitted', $this->fieldAssignmentInput)) {
            $this->fieldAssignments = AssignmentConfig::toArray(AssignmentConfig::fromInput($this->fieldAssignmentInput));
        }

        return parent::beforeValidate();
    }

    /**
     * Every size set, read from the stored configs.
     *
     * @throws InvalidSizeSetException When a stored size set is invalid.
     */
    public function catalogue(): SizeSetCatalogue
    {
        $defaults = $this->defaultFormatList();
        $sizeSets = [];

        foreach ($this->sizeSets as $handle => $config) {
            $sizeSets[] = SizeSetConfig::fromArray($handle, $config, $defaults);
        }

        return new SizeSetCatalogue($sizeSets);
    }

    /**
     * The default formats, falling back to AVIF and JPG when none are set.
     *
     * @return non-empty-list<Format>
     *
     * @throws InvalidSizeSetException When a default format is unknown.
     */
    public function defaultFormatList(): array
    {
        $formats = array_map(
            static fn(string $value): Format => (
                Format::tryFrom($value)
                ?? throw new InvalidSizeSetException(
                    "The default formats include “{$value}”, which isn't AVIF, WebP, JPG or PNG.",
                )
            ),
            $this->defaultFormats,
        );

        return [] === $formats ? [Format::Avif, Format::Jpg] : $formats;
    }

    /**
     * Leaves the CP's transient input out of project config.
     *
     * @return array<array-key, mixed>
     */
    #[Override]
    public function fields(): array
    {
        $fields = parent::fields();
        unset($fields['sizeSetRows'], $fields['fieldAssignmentInput']);

        return $fields;
    }

    /**
     * Whether a variant filesystem and prefix have both been chosen.
     */
    public function isConfigured(): bool
    {
        return '' !== $this->variantFilesystem && '' !== $this->variantPrefix;
    }

    /**
     * @return array<int, array<array-key, mixed>>
     */
    #[Override]
    public function rules(): array
    {
        return [
            [
                [
                    'autoOrient',
                    'backends',
                    'convertToSrgb',
                    'defaultFormats',
                    'fieldAssignmentInput',
                    'fieldAssignments',
                    'formatQuality',
                    'sharpenAmount',
                    'sizeSetRows',
                    'sizeSets',
                    'threadLimit',
                    'variantFilesystem',
                    'variantPrefix',
                ],
                'safe',
            ],
            ['sizeSets', 'validateSettings', 'skipOnEmpty' => false],
        ];
    }

    /**
     * The site's encoder quality per format.
     *
     * @throws InvalidSizeSetException When a stored value is out of range.
     */
    public function siteQuality(): QualityTable
    {
        return new QualityTable($this->formatQuality);
    }

    /**
     * Adds every problem {@see SettingsValidator} finds, or normalises the
     * settings when there are none.
     *
     * @throws InvalidSizeSetException Never in practice: normalising only runs once validation passes.
     */
    public function validateSettings(): void
    {
        $errors = SettingsValidator::errors($this);

        foreach ($errors as $attribute => $messages) {
            foreach ($messages as $message) {
                $this->addError($attribute, error: $message);
            }
        }

        if ([] !== $errors) {
            return;
        }

        SettingsValidator::normalise($this);
    }
}
