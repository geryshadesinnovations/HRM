<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Platform\Support\Money;

/**
 * India GST tax calculation. For a single-rate model the total tax is the rate
 * applied to the taxable amount; CGST/SGST vs IGST split is presentational and
 * derived from whether the supply is intra-state (same state as company GSTIN).
 *
 * See docs/04-BILLING.md (Invoicing).
 */
final class TaxCalculator
{
    public function rate(): float
    {
        return (float) config('billing.tax.gst_rate', 18.0);
    }

    public function taxFor(Money $taxableAmount): Money
    {
        return $taxableAmount->percentage($this->rate());
    }

    /**
     * Split the tax into components for invoice presentation.
     *
     * @return array{cgst:int,sgst:int,igst:int}
     */
    public function split(Money $tax, bool $intraState = true): array
    {
        if ($intraState) {
            $half = (int) floor($tax->minor / 2);

            return ['cgst' => $half, 'sgst' => $tax->minor - $half, 'igst' => 0];
        }

        return ['cgst' => 0, 'sgst' => 0, 'igst' => $tax->minor];
    }
}
