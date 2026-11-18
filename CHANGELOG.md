# Release Notes for Parallelogram Images

## 0.1.0 - Unreleased

### Added
- Size sets: an optional aspect ratio, a ladder of widths, a mode, formats and the `sizes` attribute, replacing per-variant "matrices". Widths an image can't fill are skipped, so nothing is upscaled.
- Field assignments keyed by field UID (optionally per field layout), deciding only what is pre-generated.
- Default formats (AVIF and JPG) for size sets that don't list their own, and an auto-orient setting (on by default).
- Variant paths: `{prefix}/{assetUid}/{hash}/{filename}.{ext}` on a chosen Craft filesystem. The filesystem and prefix have no defaults; nothing is generated or served until both are set.
- Variant hashes (HMAC with `P_IMAGES_SECRET`) covering the source file and focal point, the box, format, quality and site processing. Size sets asking for the same file share it, and only defined variants can be requested.
- Request handling for missing variant files: only variants a size set defines for the image as it is now are made. Each is generated once under a lock, saved, then sent (files on the site's domain) or redirected to (a bucket or CDN). Generation or storage failures answer 503 with Retry-After; files and redirects are cacheable for a year, not-found for five minutes.
- Imagick generator: auto-orient, sRGB conversion, crop around the focal point, resize (Lanczos), sharpen, letterbox padding (white for JPG, transparent otherwise), then AVIF, WebP, JPG (progressive) or PNG. Crop and resize geometry is worked out separately so every backend behaves the same.
- libvips generator through php-vips (2.x with PHP's FFI extension, or 1.x with the `vips` extension): same geometry, EXIF orientation, sRGB through embedded ICC profiles, Lanczos resizing, sharpening and padding. Auto now picks libvips first when php-vips is installed and its extension (FFI or `vips`) is enabled at runtime, then ImageMagick, then GD.
- GD generator for servers without ImageMagick: same resize geometry, EXIF orientation (matching ImageMagick's for all eight values), sharpening and letterboxing, encoding AVIF, WebP, progressive JPG or PNG. GD can't convert colour profiles, so the sRGB setting has no effect with it.
- imgproxy URLs, signed with the server's hex key and salt, mapping every mode onto imgproxy's resizing (focal-point crops, never enlarging) and the site's processing onto auto-rotate, sRGB and sharpen options.
- Cloudflare Images transform-via-URL links. Cloudflare can't output PNG, may enlarge letterboxed or stretched images, and always pads with white.
- Backend choice: site-wide (auto: ImageMagick, then GD; or imgproxy or Cloudflare) with per-volume overrides. The imgproxy key and salt must be environment variables.
- The variant route: `{prefix}/…` requests the web server can't satisfy are answered by the plugin once a filesystem and prefix are chosen. A Delivery tab in the settings covers the filesystem, prefix, backends and service details.
- `asset.picture('handle', {...})`: a `<picture>` with a `<source>` per modern format and an `<img>` in the fallback format, each `srcset` describing files by the width they really have. Options: `alt`, `class`, `sizes`, `lazysrc` (ParallelogramJS `data-lazysrc-*` markup with a `<noscript>` copy), `priority` (`fetchpriority="high"`, never lazy), `attributes` and art-directed `sources` (`{media, asset, sizeSet}`). Unknown or mistyped options are errors.
- `pictureClass` and `pictureAttributes` options for the `<picture>` element itself, since many sites style it. A picture that falls back to the original file keeps its `<picture>` wrapper when these are set.
- Art-directed `sources` entries can be `{media, blank: true}`, which show a transparent pixel while the media query matches, so small screens don't download an image they never show.
- `asset.variants('handle')`: the same sources as data, e.g. `asset.variants('card').srcset('avif')`.
- Alternate images: each size set can name an Assets field on the asset's own layout (found by UID, so overridden handles work), a media query and an optional size set. When the field has an image, `asset.picture()` adds it as an art-directed source; a template's `sources` option replaces it, and `sources: []` turns it off.
- A Placeholder Colour field type. Added to a volume's field layout, it holds each image's most common colour (similar colours grouped, the largest group averaged), worked out by a queue job whenever an image is uploaded or replaced and stored straight into the asset's content without an element save. `asset.picture()` fills the `<picture>` with it until the image loads (`style="background-color:…"`, which a template's own `pictureAttributes` style replaces), and `asset.variants()` has it as `colour`.
- `php craft p-images/placeholders [--force]` fills in placeholder colours for images already uploaded.
- Removing outdated variants when an image's file is replaced (its variant URLs change with the file) and all of an image's variants when it is deleted, both as queue jobs. Nothing is made at those times: variants are made when first requested, or by `p-images/warm`. Nothing is deleted while no size sets are defined.
- Console commands: `p-images/warm` (makes the size sets assigned on the Pre-generate tab, e.g. after a deploy), `audit` (exits non-zero when variants are missing, to guard a deploy), `rebuild` and `gc` (removes variants of deleted images and out-of-date files), with `--volume` and, for warm and audit, `--size-set`.
- Utilities → Parallelogram Images, with buttons that queue making missing variants or removing unused ones, and an Assets index action that rebuilds the selected images' variants. Both need the `utility:p-images-warm` permission.
- A "Use Lazysrc by default" setting; templates opt out with `lazysrc: false`.
- Until variants are configured, and for SVGs, GIFs and other files that aren't resized, pictures show the original file, with an explaining HTML comment in dev mode. An unknown size set or option is an error in dev mode; in production it's logged and the original is shown.
- Pages link straight to variant files served from the site's own domain, to the site's variant route (which redirects) for files kept in a bucket or CDN, and to imgproxy or Cloudflare for volumes that use them.
- A Craft integration suite (`phpunit.craft.xml`) that boots the sandbox site against its own test database.
- `P_IMAGES_SECRET` is written to `.env` on install if it isn't already set.
- `PrototypeConverter`, which turns the prototype's matrices and field-handle assignments into size sets and UID-keyed assignments, with notes on anything that couldn't carry over.

- A global AVIF effort setting (0–9, default 3), applied by ImageMagick, libvips and GD; imgproxy and Cloudflare ignore it. Quality is the existing per-format site setting (AVIF 60 by default). Effort changes how hard the encoder works, not the picture, so changing it leaves existing variants valid; rebuild to apply it to them. Backends write different bytes even at the same settings.
- `Plugin::EVENT_AFTER_SAVE_PLACEHOLDER`, fired with a `PlaceholderSavedEvent` (the asset and colour) after a placeholder colour is stored, for caches and CDNs to refresh the pages showing the image. When Blitz is installed the plugin listens and refreshes its cached pages that show the image; Blitz is never required.
- The libvips check accepts php-vips 1.x with the `vips` PHP extension as well as 2.x with FFI.
- `LICENSE.md` notes that the plugin is free to use for now and the terms may change from the first paid release.

### Changed
- Split out of the client project where it began as "Parallelogram Image Variants", keeping its history.
- Renamed to Parallelogram Images: package `parallelogram/craft-images`, handle `p-images`, namespace `parallelogram\images`.
- Licensed under the Craft Licence.
- Tooling moved to Mago (format, lint, analyse) and PHPUnit 12, with tests in `test/`.
- New installs start with no size sets; the prototype's seeded sets were specific to one site.
- The placeholder colour is written on the `<picture>` element, not the `<img>`: sites commonly hide the image until it has loaded, which hid its colour too. A `style` passed in `attributes` now stays on the `<img>`; one in `pictureAttributes` replaces the colour.

### Fixed
- Sites with FFI enabled but without php-vips installed crashed on every variant request with "Class Jcupitt\Vips\Config not found". The libvips check now only touches php-vips when it runs, and falls back to ImageMagick or GD as intended.

### Removed
- The prototype's warming services, CP progress tracking with its Stop button and cached coverage report, and asset-save hooks, replaced by the queue-based warming above.
- The bundled 1998 HP sRGB profile, replaced by the ICC's freely redistributable sRGB v4 preference profile (see THIRD-PARTY-NOTICES.md).
- Moving variants when an asset is moved or renamed, which UID-based paths make unnecessary.
- The prototype's `craft.pImages.variantSet()` Twig variable, replaced by `asset.picture()` and `asset.variants()`.
- Writing variants into Craft's transform paths.
- Making variants automatically when an image is uploaded, or when an element is saved with images in assigned fields. Pre-generate with `p-images/warm` instead.
- The "fast generate" setting, which is replaced by the backend choice in a later step.
