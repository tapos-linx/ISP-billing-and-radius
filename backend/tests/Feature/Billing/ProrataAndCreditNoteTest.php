<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Contracts\Billing\ProrataCalculatorInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProrataAndCreditNoteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.testing' => true]);
    }

    public function test_prorata_calculator_computes_mid_cycle_days_and_nbr_vat(): void
    {
        $calculator = app(ProrataCalculatorInterface::class);

        // 1000 monthly fee, 30-day month, 15 active days, 5% Bangladesh VAT
        $result = $calculator->calculateProration(
            monthlyFee: 1000.0,
            billingCycleDays: 30,
            activeDays: 15,
            vatRate: 5.0
        );

        $this->assertSame(1000.0, $result['monthly_fee']);
        $this->assertSame(30, $result['billing_cycle_days']);
        $this->assertSame(15, $result['active_days']);
        $this->assertEquals(33.3333, $result['daily_rate']);
        $this->assertEquals(500.0, $result['subtotal_bdt']);
        $this->assertEquals(25.0, $result['vat_amount_bdt']);
        $this->assertEquals(525.0, $result['total_bdt']);
    }

    public function test_generate_credit_note_creates_persisted_audit_record(): void
    {
        $calculator = app(ProrataCalculatorInterface::class);
        $subscriberId = (string) Str::uuid();
        $invoiceId = (string) Str::uuid();

        // 1. Seed subscriber & invoice
        DB::table('subscribers')->insert([
            'subscriber_id' => $subscriberId,
            'tenant_id' => 1,
            'username' => 'credit_user_'.Str::random(6),
            'status' => 'active',
            'expires_at' => now()->addMonth(),
            'created_at' => now(),
        ]);

        DB::table('invoices')->insert([
            'invoice_id' => $invoiceId,
            'subscriber_id' => $subscriberId,
            'tenant_id' => 1,
            'subtotal_bdt' => 1000.00,
            'vat_rate_percent' => 5.00,
            'vat_amount_bdt' => 50.00,
            'total_bdt' => 1050.00,
            'amount_bdt' => 1050.00,
            'status' => 'paid',
            'due_date' => now(),
            'created_at' => now(),
        ]);

        // 2. Issue Credit Note of BDT 525 (gross)
        $creditNote = $calculator->generateCreditNote(
            invoiceId: $invoiceId,
            refundAmount: 525.0,
            reason: 'Fiber outage rebate for 15 days downtime',
            tenantId: 1
        );

        $this->assertNotNull($creditNote['credit_note_id']);
        $this->assertStringStartsWith('CN-', $creditNote['credit_note_number']);
        $this->assertSame('issued', $creditNote['status']);
        $this->assertEquals(525.0, $creditNote['total_bdt']);

        // 3. Verify database row in credit_notes table
        $dbRecord = DB::table('credit_notes')->where('id', $creditNote['credit_note_id'])->first();
        $this->assertNotNull($dbRecord);
        $this->assertSame($creditNote['credit_note_number'], $dbRecord->credit_note_number);
        $this->assertSame('Fiber outage rebate for 15 days downtime', $dbRecord->reason);
        $this->assertSame($subscriberId, (string) $dbRecord->subscriber_id);
    }
}
