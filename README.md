# Parallelogram Images

Responsive image variants for Craft CMS. You define named size sets (an aspect ratio and a ladder of widths), the plugin pre-generates AVIF and JPEG files for them, and templates output `<picture>` markup for native lazy loading or ParallelogramJS's Lazysrc.

> **Status:** being rebuilt from an in-production prototype to the agreed v1 design. See `craft-optimiser/DECISIONS.md` in the Parallelogram workspace. Not yet listed on the Craft Plugin Store.

## Requirements

- PHP 8.3 or later
- Craft CMS 5.6 or later
- Imagick or php-vips (FFI) for local generation, or an imgproxy or Cloudflare Images account

## Templates

```twig
{# A <picture> in the "card" size set: native lazy loading #}
{{ asset.picture('card', {class: 'card__image'}) }}

{# The largest image above the fold loads straight away #}
{{ entry.hero.one().picture('hero', {priority: true}) }}

{# ParallelogramJS Lazysrc markup, plus a square crop on phones #}
{{ asset.picture('wide', {
    lazysrc: true,
    sources: [{media: '(max-width: 600px)', sizeSet: 'square'}],
}) }}

{# The sources as data, for markup of your own #}
{% set variants = asset.variants('card') %}
<img src="{{ variants.fallback.url }}" srcset="{{ variants.srcset('jpg') }}" sizes="{{ variants.sizes }}"
     width="{{ variants.size.width }}" height="{{ variants.size.height }}" alt="">
```

Options for `asset.picture()`: `alt` (defaults to the asset's alt text), `class`, `sizes` (defaults to the size set's), `lazysrc`, `priority`, `attributes` (any other `<img>` attributes) and `sources` (`{media, asset, sizeSet}`, where `asset` and `sizeSet` default to the picture's own). The "Use Lazysrc by default" setting makes `lazysrc: true` the default.

A size set can also have an alternate image (Settings → Size sets → Alternate images): an Assets field on the volume's layout, a media query and optionally another size set. When an asset has an image in that field, `asset.picture()` shows it while the media query matches, unless the template passes its own `sources`.

For a colour behind each image while it loads, add a **Placeholder Colour** field to the volume's field layout. Colours are worked out as images are uploaded; run `php craft p-images/placeholders` once for images already there.

Until a variant filesystem and prefix are chosen, and for SVGs and GIFs, `asset.picture()` shows the original file. An unknown size set or option is an error in dev mode; in production it's logged and the original is shown.

## Development

```bash
composer install
composer check
```

### Craft integration tests

Tests that need a running Craft site live in `test/Craft` and run from the sandbox project, which requires this plugin through a path repository, against a separate `craft_images_test` database:

```bash
cd ../parallelogram-craft-sandbox
bash vendor/parallelogram/craft-images/test/Craft/install-test-db.sh   # once
vendor/bin/phpunit -c vendor/parallelogram/craft-images/phpunit.craft.xml
```

Each test runs in a rolled-back transaction, project config is never written back to the sandbox, and the test volume and variant folders are emptied afterwards.

The files listed under `excludes` in `mago.toml` are prototype code that hasn't been reworked yet. A file comes off that list once it has been rebuilt, is Mago-clean and has full test coverage. The list must be empty before v1.

## Licence

The Craft Licence. See [LICENSE.md](LICENSE.md).
