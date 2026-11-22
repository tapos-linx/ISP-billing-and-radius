<?php

declare(strict_types=1);

namespace App\Contracts\Billing;

interface ProrataCalculatorInterface
{
    /**
     * Calculate mid-cycle pro-rata charges with Bangladesh NBR VAT.
     *
     * @return array{
     *     monthly_fee: float,
     *     billing_cycle_days: int,
     *     active_days: int,
     *     daily_rate: float,
     *     subtotal_bdt: float,
     *     vat_rate_percent: float,
     *     vat_amount_bdt: float,
     *     total_bdt: float
     * }
     */
    public function calculateProration(
        float $monthlyFee,
        int $billingCycleDays,
        int $activeDays,
        float $vatRate = 5.0
    ): array;

    /**
     * Issue a formal NBR-compliant credit note against an invoice.
     *
     * @return array{
     *     credit_note_id: int,
     *     credit_note_number: string,
     *     subtotal_bdt: float,
     *     vat_bdt: float,
     *     total_bdt: float,
     *     status: string
     * }
     */
    public function generateCreditNote(
        string $invoiceId,
        float $refundAmount,
        string $reason,
        int $tenantId = 1
    ): array;
}
