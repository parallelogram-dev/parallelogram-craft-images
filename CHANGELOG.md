# Release Notes for Parallelogram Images

## Unreleased

### Added
- Size sets: an optional aspect ratio, a ladder of widths, a mode, formats and the `sizes` attribute, replacing per-variant "matrices". Widths an image can't fill are skipped, so nothing is upscaled.
- Field assignments keyed by field UID (optionally per field layout), deciding only what is pre-generated.
- Default formats (AVIF and JPG) for size sets that don't list their own, and an auto-orient setting (on by default).
- `PrototypeConverter`, which turns the prototype's matrices and field-handle assignments into size sets and UID-keyed assignments, with notes on anything that couldn't carry over.

### Changed
- Split out of the client project where it began as "Parallelogram Image Variants", keeping its history.
- Renamed to Parallelogram Images: package `parallelogram/craft-images`, handle `p-images`, namespace `parallelogram\images`, Twig variable `craft.pImages`.
- Licensed under the Craft Licence.
- Tooling moved to Mago (format, lint, analyse) and PHPUnit 12, with tests in `test/`.
- New installs start with no size sets; the prototype's seeded sets were specific to one site.

### Removed
- The "fast generate" setting, which is replaced by the backend choice in a later step.
