<?php

declare(strict_types=1);

namespace parallelogram\images\warming;

use parallelogram\images\delivery\MakeResult;

/**
 * How many variant files warming made, found already there, or couldn't make.
 *
 * @api
 */
final readonly class WarmResult
{
    public function __construct(
        public int $made = 0,
        public int $present = 0,
        public int $failed = 0,
    ) {}

    public function plus(self $other): self
    {
        return new self($this->made + $other->made, $this->present + $other->present, $this->failed + $other->failed);
    }

    public function with(MakeResult $result): self
    {
        if (MakeResult::Made === $result) {
            return new self($this->made + 1, $this->present, $this->failed);
        }

        if (MakeResult::Present === $result) {
            return new self($this->made, $this->present + 1, $this->failed);
        }

        return new self($this->made, $this->present, $this->failed + 1);
    }
}
