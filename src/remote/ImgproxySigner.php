<?php

declare(strict_types=1);

namespace parallelogram\images\remote;

use parallelogram\images\variants\InvalidVariantException;
use SensitiveParameter;

use function base64_encode;
use function ctype_xdigit;
use function hash_hmac;
use function hex2bin;
use function rtrim;
use function strlen;
use function strtoupper;
use function strtr;

/**
 * Signs imgproxy paths: HMAC-SHA256 of the salt followed by the path, keyed
 * with the key, both given as hex as imgproxy's own settings are, and encoded
 * as URL-safe Base64 without padding.
 *
 * @api
 */
final readonly class ImgproxySigner
{
    private string $key;

    private string $salt;

    /**
     * @throws InvalidVariantException When the key or salt isn't hex.
     */
    public function __construct(
        #[SensitiveParameter]
        string $keyHex,
        #[SensitiveParameter]
        string $saltHex,
    ) {
        $this->key  = self::decode($keyHex, name: 'key');
        $this->salt = self::decode($saltHex, name: 'salt');
    }

    /**
     * @throws InvalidVariantException
     */
    private static function decode(string $hex, string $name): string
    {
        $bytes = '' !== $hex && 0 === (strlen($hex) % 2) && ctype_xdigit($hex) ? hex2bin($hex) : false;

        if (false === $bytes) {
            throw new InvalidVariantException(
                "The imgproxy {$name} must be hex, as in imgproxy's IMGPROXY_" . strtoupper($name) . '.',
            );
        }

        return $bytes;
    }

    /**
     * The signature for a path that starts with a slash, e.g.
     * `/rs:fill:300:400:0/aHR0cDov….avif`.
     */
    public function sign(string $path): string
    {
        $digest = hash_hmac('sha256', $this->salt . $path, $this->key, binary: true);

        return rtrim(strtr(base64_encode($digest), from: '+/', to: '-_'), characters: '=');
    }
}
