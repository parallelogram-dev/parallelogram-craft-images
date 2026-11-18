# Parallelogram Images

Responsive image variants for Craft CMS. You define named size sets (an aspect ratio and a ladder of widths), the plugin pre-generates AVIF and JPEG files for them, and templates output `<picture>` markup for native lazy loading or ParallelogramJS's Lazysrc.

> **Status:** being rebuilt from an in-production prototype to the agreed v1 design. See `craft-optimiser/DECISIONS.md` in the Parallelogram workspace. Not yet listed on the Craft Plugin Store.

## Requirements

- PHP 8.3 or later
- Craft CMS 5.6 or later
- Imagick or php-vips (FFI) for local generation, or an imgproxy or Cloudflare Images account

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
