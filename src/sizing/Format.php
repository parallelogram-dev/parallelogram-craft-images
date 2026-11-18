<?php

declare(strict_types=1);

namespace parallelogram\images\sizing;

/**
 * An output image format.
 *
 * @api
 */
enum Format: string
{
    case Avif = 'avif';
    case Jpg  = 'jpg';
    case Png  = 'png';
    case Webp = 'webp';

    /**
     * Encoder quality used when neither the site nor the size set sets one.
     * AVIF and WebP reach comparable visual quality at lower numbers than JPEG.
     */
    public function defaultQuality(): int
    {
        if (self::Avif === $this) {
            return 60;
        }

        return self::Jpg === $this ? 82 : 80;
    }

    /**
     * Whether every browser can show it, so it can be the `<img>` fallback.
     */
    public function isUniversal(): bool
    {
        return self::Jpg === $this || self::Png === $this;
    }

    public function mimeType(): string
    {
        return 'image/' . (self::Jpg === $this ? 'jpeg' : $this->value);
    }
}
