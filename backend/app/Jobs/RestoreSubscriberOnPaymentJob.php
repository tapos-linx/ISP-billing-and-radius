<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Billing\DunningStateMachineInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RestoreSubscriberOnPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 5;

    public function __construct(
        public string $subscriberId,
        public string $invoiceId,
        public string $gateway,
        public float $amountBdt,
        public int $tenantId = 1
    ) {
        $this->onQueue('critical');
    }

    public function handle(DunningStateMachineInterface $dunningService): void
    {
        $startTime = microtime(true);

        // 1. Mark invoice as paid
        DB::table('invoices')
            ->where('invoice_id', $this->invoiceId)
            ->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

        // 2. Extend subscriber expiration by 30 days
        DB::table('subscribers')
            ->where('subscriber_id', $this->subscriberId)
            ->update([
                'expires_at' => now()->addDays(30),
                'grace_until' => null,
            ]);

        // 3. Transition dunning state back to 'active' (removes walled garden + kicks CoA session)
        $dunningService->transition(
            $this->subscriberId,
            'active',
            "Payment received via {$this->gateway} for invoice {$this->invoiceId} (BDT {$this->amountBdt})"
        );

        $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info("RestoreSubscriberOnPaymentJob unblocked {$this->subscriberId} in {$elapsedMs}ms (<3s SLA)");
    }
}
