<?php

declare(strict_types=1);

namespace App\Platform\Support;

use InvalidArgumentException;

/**
 * Immutable money value object. Amount is always integer minor units (paise),
 * never a float. Used across billing and payroll to avoid rounding bugs.
 *
 * See docs/04-BILLING.md.
 */
final class Money
{
    public function __construct(
        public readonly int $minor,
        public readonly string $currency = 'INR',
    ) {
        if ($minor < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }
    }

    public static function of(int $minor, string $currency = 'INR'): self
    {
        return new self($minor, $currency);
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->minor * $factor, $this->currency);
    }

    /** Apply a percentage (e.g. 18.00 for GST) and round to the nearest minor unit. */
    public function percentage(float $rate): self
    {
        return new self((int) round($this->minor * $rate / 100), $this->currency);
    }

    public function format(): string
    {
        return number_format($this->minor / 100, 2).' '.$this->currency;
    }

    public function toArray(): array
    {
        return ['minor' => $this->minor, 'currency' => $this->currency];
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException('Currency mismatch.');
        }
    }
}
