<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Billing\ProrataCalculatorInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProrataCalculator implements ProrataCalculatorInterface
{
    /**
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
    ): array {
        if ($billingCycleDays <= 0) {
            throw new InvalidArgumentException('Billing cycle days must be greater than zero.');
        }

        $activeDays = max(0, min($activeDays, $billingCycleDays));
        $dailyRate = round($monthlyFee / $billingCycleDays, 4);
        $subtotal = round($dailyRate * $activeDays, 2);
        $vatAmount = round($subtotal * ($vatRate / 100.0), 2);
        $total = round($subtotal + $vatAmount, 2);

        return [
            'monthly_fee' => round($monthlyFee, 2),
            'billing_cycle_days' => $billingCycleDays,
            'active_days' => $activeDays,
            'daily_rate' => $dailyRate,
            'subtotal_bdt' => $subtotal,
            'vat_rate_percent' => $vatRate,
            'vat_amount_bdt' => $vatAmount,
            'total_bdt' => $total,
        ];
    }

    /**
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
    ): array {
        $invoice = DB::table('invoices')
            ->where('invoice_id', $invoiceId)
            ->first();

        if (! $invoice) {
            throw new InvalidArgumentException("Invoice not found: {$invoiceId}");
        }

        $vatRate = (float) ($invoice->vat_rate_percent ?? 5.0);
        // Exclude VAT from refund amount if gross:
        $subtotal = round($refundAmount / (1.0 + ($vatRate / 100.0)), 2);
        $vatAmount = round($refundAmount - $subtotal, 2);
        $total = round($subtotal + $vatAmount, 2);

        $creditNoteNumber = 'CN-'.date('Ymd').'-'.strtoupper(Str::random(6));

        $id = (int) DB::table('credit_notes')->insertGetId([
            'credit_note_number' => $creditNoteNumber,
            'tenant_id' => $tenantId,
            'invoice_id' => $invoiceId,
            'subscriber_id' => (string) $invoice->subscriber_id,
            'subtotal_bdt' => $subtotal,
            'vat_bdt' => $vatAmount,
            'total_bdt' => $total,
            'reason' => $reason,
            'status' => 'issued',
            'created_at' => now(),
        ]);

        return [
            'credit_note_id' => $id,
            'credit_note_number' => $creditNoteNumber,
            'subtotal_bdt' => $subtotal,
            'vat_bdt' => $vatAmount,
            'total_bdt' => $total,
            'status' => 'issued',
        ];
    }
}
