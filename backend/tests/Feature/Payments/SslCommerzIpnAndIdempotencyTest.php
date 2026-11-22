<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Jobs\RestoreSubscriberOnPaymentJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SslCommerzIpnAndIdempotencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.testing' => true]);
    }

    public function test_sslcommerz_ipn_processes_payment_and_dispatches_instant_unblock(): void
    {
        Queue::fake([RestoreSubscriberOnPaymentJob::class]);

        $subscriberId = (string) Str::uuid();
        $invoiceId = (string) Str::uuid();
        $tranId = 'SSL_TX_'.Str::random(12);

        // 1. Seed subscriber & unpaid invoice
        DB::table('subscribers')->insert([
            'subscriber_id' => $subscriberId,
            'tenant_id' => 1,
            'username' => 'ssl_sub_'.Str::random(6),
            'status' => 'expired',
            'dunning_state' => 'expired',
            'expires_at' => now()->subDays(2),
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
            'status' => 'unpaid',
            'due_date' => now(),
            'created_at' => now(),
        ]);

        $ipnPayload = [
            'tran_id' => $tranId,
            'val_id' => 'VAL_'.$tranId,
            'amount' => 1050.00,
            'currency' => 'BDT',
            'status' => 'VALID',
            'value_a' => $subscriberId,
            'value_b' => $invoiceId,
        ];

        // 2. Submit IPN Webhook
        $response = $this->postJson('/api/v1/payments/card/sslcommerz/ipn', $ipnPayload);
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'tran_id' => $tranId,
        ]);

        // 3. Verify payment_webhook_logs record
        $log = DB::table('payment_webhook_logs')
            ->where('gateway', 'sslcommerz')
            ->where('idempotency_key', $tranId)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('processed', $log->status);

        // 4. Verify payment row created
        $payment = DB::table('payments')->where('trx_id', $tranId)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(1050.00, (float) $payment->amount_bdt);

        // 5. Verify RestoreSubscriberOnPaymentJob pushed on 'critical' queue (<3s SLA)
        Queue::assertPushedOn('critical', RestoreSubscriberOnPaymentJob::class, function ($job) use ($subscriberId, $invoiceId) {
            return $job->subscriberId === $subscriberId && $job->invoiceId === $invoiceId;
        });

        // 6. Test Idempotency Replay Protection: exact same transaction re-posted
        $replayResponse = $this->postJson('/api/v1/payments/card/sslcommerz/ipn', $ipnPayload);
        $replayResponse->assertStatus(409);
        $replayResponse->assertJson(['status' => 'duplicate_ignored']);
    }
}
