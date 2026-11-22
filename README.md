# Parallelogram Images

Responsive image variants for Craft CMS. You define named size sets (an aspect ratio and a ladder of widths), the plugin pre-generates AVIF and JPEG files for them, and templates output `<picture>` markup for native lazy loading or ParallelogramJS's Lazysrc.

> **Status:** being rebuilt from an in-production prototype to the agreed v1 design. See `craft-optimiser/DECISIONS.md` in the Parallelogram workspace. Not yet listed on the Craft Plugin Store.

## Requirements

- PHP 8.3 or later
- Craft CMS 5.6 or later
- ImageMagick (the `imagick` extension), GD, or libvips for local generation, or an imgproxy or Cloudflare Images account
- A queue runner, as Craft recommends for any site (see [Server setup](#server-setup))

libvips is the fastest and least memory-hungry. It needs `jcupitt/vips` and one of two PHP extensions: **2.x with FFI** (`ffi.enable` on, not just preloading), or **1.x with the `vips` PECL extension**. Both versions share one API, so the plugin works with either; Composer picks the one your PHP supports. With the backend on Auto, libvips is used whenever it can be loaded, then ImageMagick, then GD.

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

Options for `asset.picture()`: `alt` (defaults to the asset's alt text), `class` and `attributes` (for the `<img>`), `pictureClass` and `pictureAttributes` (for the `<picture>` around it), `sizes` (defaults to the size set's), `lazysrc`, `priority`, `attributes` (any other `<img>` attributes) and `sources` (`{media, asset, sizeSet}`, where `asset` and `sizeSet` default to the picture's own, or `{media, blank: true}` to show nothing while the media query matches). The "Use Lazysrc by default" setting makes `lazysrc: true` the default.

A size set can also have an alternate image (Settings → Size sets → Alternate images): an Assets field on the volume's layout, a media query and optionally another size set. When an asset has an image in that field, `asset.picture()` shows it while the media query matches, unless the template passes its own `sources`.

For a colour behind each image while it loads, add a **Placeholder Colour** field to the volume's field layout. Colours are worked out as images are uploaded; run `php craft p-images/placeholders` once for images already there. The colour is written on the `<picture>` element as `style="background-color:…"`, not the `<img>`, because sites often hide the image until it has loaded. A `style` in `pictureAttributes` replaces it.

When a colour is stored, the plugin fires `Plugin::EVENT_AFTER_SAVE_PLACEHOLDER` with a `PlaceholderSavedEvent` (the asset and its colour), so a page cache or CDN can refresh pages showing the image. If Blitz is installed it is refreshed automatically; no other plugin is needed or required:

```php
Event::on(Plugin::class, Plugin::EVENT_AFTER_SAVE_PLACEHOLDER, function (PlaceholderSavedEvent $event) {
    // $event->asset, $event->colour
});
```

Until a variant filesystem and prefix are chosen, and for SVGs and GIFs, `asset.picture()` shows the original file. An unknown size set or option is an error in dev mode; in production it's logged and the original is shown.

## Making and removing variants

Variants are made the first time they're asked for: the page links straight to the file, and when it doesn't exist yet the web server hands the request to Craft, which makes and saves it, then serves it as a static file from then on. **Nothing is made when an image is uploaded or an entry is saved.** To have files ready before the first visitor, for instance after a deploy, run `p-images/warm`, which makes the size sets chosen on the Pre-generate tab for the images in those fields.

When an image's file is replaced, a queue job removes its old variants (their URLs change with the file), and deleting an image removes all of them.

```bash
php craft p-images/warm [--volume=handle] [--size-set=handle]   # make missing variants
php craft p-images/audit [--volume=handle] [--size-set=handle]  # count missing ones; exits 1 if any (a deploy guard)
php craft p-images/rebuild [--volume=handle]                    # delete and remake
php craft p-images/gc                                           # remove variants nothing can ask for
```

Utilities → Parallelogram Images queues the same work, and the Assets index has a "Rebuild image variants" action. They need the `utility:p-images-warm` permission.

## Encoding

Quality is set per format for the whole site (AVIF 50, WebP 80, JPG 82 by default; a size set can override it). **AVIF effort** (Settings, 0–9, default 3) is how hard the encoder works: higher is slower and gives smaller files. It applies to ImageMagick, libvips and GD, and imgproxy and Cloudflare ignore it. Effort changes the file, not the picture, so changing it leaves existing variants valid; run `p-images/rebuild` to apply it to them.

Backends use different AVIF encoders and settings, so the same image at the same quality and effort is not byte-identical, or always the same size, between them. AVIF encoding dominates generation time: allow several seconds per large image on ImageMagick, far less with libvips.

## Server setup

**Web server.** Variant files are served by the web server when they exist, and sent to Craft when they don't. With nginx, add this to the site's Craft configuration, before any rule that returns 404 for missing static files:

```nginx
location ^~ /asset/variant/ {
    try_files $uri /index.php?$query_string;
    expires 1y;
    add_header Cache-Control "public, immutable";
    add_header X-Content-Type-Options "nosniff" always;
}
```

Use your own variant prefix in place of `/asset/variant/`. Also make sure `image/avif` is in nginx's `mime.types`, or AVIF files are sent as `application/octet-stream`.

**Console and queue.** Craft can't tell where the web root is from the command line, and variants are written relative to it. Set `CRAFT_WEB_ROOT` in `.env` (for example `/var/www/site/web`) so console commands and queue jobs write to the same place as web requests. Without it they fail with "Couldn't read the original file".

**Queue runner.** Placeholder colours and removing outdated variants are queue jobs, so a runner must be working (`php craft queue/listen`, or `queue/run` from cron). Work already queued at the time you install the plugin runs then too.

**Threads.** The thread limit setting caps the threads each ImageMagick encode may use. ImageMagick uses every core by default, so on a busy server set it a few below your core count, or nginx and PHP-FPM compete with the encoder.

**Behind Cloudflare or another proxy.** If TLS ends at the proxy, the origin sees plain HTTP, and Craft builds URLs from the request. Blitz's file cache and SEOmatic's meta cache are keyed without the scheme, so a request that arrives as `http://` (a health check, `curl` on the server) can cache pages whose variant, canonical and `og:` URLs are all `http://`, and serve them to everyone. Pass the real scheme when testing an origin:

```bash
curl -H "Host: example.com" -H "X-Forwarded-Proto: https" http://127.0.0.1/
```

If it has happened, clear Craft's data caches and Blitz, rebuild the affected pages with that header, and purge the CDN.

**libvips under PHP-FPM.** On one development machine, php-vips with FFI crashed PHP-FPM (signal 6) on every encode while the same code worked from the command line. The machine had libvips from micromamba and ImageMagick from Homebrew, and the cause is unproven, most likely two incompatible copies of a shared library in one process. If FPM crashes with libvips selected, choose ImageMagick under Settings → Delivery and check which libraries FPM loads.

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


## Licence

The Craft Licence. See [LICENSE.md](LICENSE.md).
