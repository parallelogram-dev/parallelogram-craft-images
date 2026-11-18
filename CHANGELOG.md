# Release Notes for Parallelogram Images

## Unreleased

### Added
- Size sets: an optional aspect ratio, a ladder of widths, a mode, formats and the `sizes` attribute, replacing per-variant "matrices". Widths an image can't fill are skipped, so nothing is upscaled.
- Field assignments keyed by field UID (optionally per field layout), deciding only what is pre-generated.
- Default formats (AVIF and JPG) for size sets that don't list their own, and an auto-orient setting (on by default).
- Variant paths: `{prefix}/{assetUid}/{hash}/{filename}.{ext}` on a chosen Craft filesystem. The filesystem and prefix have no defaults; nothing is generated or served until both are set.
- Variant hashes (HMAC with `P_IMAGES_SECRET`) covering the source file and focal point, the box, format, quality and site processing. Size sets asking for the same file share it, and only defined variants can be requested.
- Request handling for missing variant files: only variants a size set defines for the image as it is now are made. Each is generated once under a lock, saved, then sent (files on the site's domain) or redirected to (a bucket or CDN). Generation or storage failures answer 503 with Retry-After; files and redirects are cacheable for a year, not-found for five minutes.
- Imagick generator: auto-orient, sRGB conversion, crop around the focal point, resize (Lanczos), sharpen, letterbox padding (white for JPG, transparent otherwise), then AVIF, WebP, JPG (progressive) or PNG. Crop and resize geometry is worked out separately so every backend behaves the same.
- `P_IMAGES_SECRET` is written to `.env` on install if it isn't already set.
- `PrototypeConverter`, which turns the prototype's matrices and field-handle assignments into size sets and UID-keyed assignments, with notes on anything that couldn't carry over.

### Changed
- Split out of the client project where it began as "Parallelogram Image Variants", keeping its history.
- Renamed to Parallelogram Images: package `parallelogram/craft-images`, handle `p-images`, namespace `parallelogram\images`, Twig variable `craft.pImages`.
- Licensed under the Craft Licence.
- Tooling moved to Mago (format, lint, analyse) and PHPUnit 12, with tests in `test/`.
- New installs start with no size sets; the prototype's seeded sets were specific to one site.

### Removed
- The bundled 1998 HP sRGB profile, replaced by the ICC's freely redistributable sRGB v4 preference profile (see THIRD-PARTY-NOTICES.md).
- Moving variants when an asset is moved or renamed, which UID-based paths make unnecessary.
- Writing variants into Craft's transform paths.
- The "fast generate" setting, which is replaced by the backend choice in a later step.
