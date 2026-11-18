<?php

declare(strict_types=1);

namespace parallelogram\images\variants;

use parallelogram\images\sizing\Variant;
use SensitiveParameter;

use function hash_hmac;
use function implode;
use function substr;

/**
 * Computes the hash in a variant's path. It covers everything that changes the
 * file's pixels: the source file and focal point, the box, format and quality,
 * and site-wide processing. Mixing in a secret means only variants the site
 * defines can ever be requested.
 *
 * Two size sets that ask for the same box, format and quality share one file.
 *
 * @api
 */
final readonly class VariantHasher
{
    /**
     * Bumped whenever the hashed values or their order change.
     */
    private const string SCHEME = 'v1';

    private const int LENGTH = 16;

    /**
     * @throws InvalidVariantException When the secret is empty.
     */
    public function __construct(
        #[SensitiveParameter]
        private string $secret,
        private Processing $processing,
    ) {
        if ('' === $secret) {
            throw new InvalidVariantException('Image variants need a secret. Set P_IMAGES_SECRET in .env.');
        }
    }

    public function hash(SourceImage $source, Variant $variant): string
    {
        $parts = [
            self::SCHEME,
            $source->uid,
            (string) $source->modifiedAt,
            $source->focalPoint->toString(),
            (string) $variant->box->width,
            null === $variant->box->height ? 'auto' : (string) $variant->box->height,
            $variant->box->mode->value,
            $variant->format->value,
            (string) $variant->quality,
            $this->processing->toString(),
        ];

        return substr(hash_hmac('sha256', implode('|', $parts), $this->secret), offset: 0, length: self::LENGTH);
    }
}
