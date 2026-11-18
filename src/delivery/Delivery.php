<?php

declare(strict_types=1);

namespace parallelogram\images\delivery;

/**
 * The answer to a variant request: its outcome, and the file or URL that goes
 * with it.
 *
 * @api
 */
final readonly class Delivery
{
    private function __construct(
        public DeliveryOutcome $outcome,
        public string $body = '',
        public string $mimeType = '',
        public string $location = '',
    ) {}

    public static function content(string $body, string $mimeType): self
    {
        return new self(DeliveryOutcome::Content, $body, $mimeType);
    }

    public static function notFound(): self
    {
        return new self(DeliveryOutcome::NotFound);
    }

    public static function redirect(string $location): self
    {
        return new self(DeliveryOutcome::Redirect, location: $location);
    }

    public static function unavailable(): self
    {
        return new self(DeliveryOutcome::Unavailable);
    }
}
