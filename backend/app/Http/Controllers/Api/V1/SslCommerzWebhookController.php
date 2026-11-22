<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Payments\WebhookIdempotencyInterface;
use App\Http\Controllers\Controller;
use App\Jobs\RestoreSubscriberOnPaymentJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SslCommerzWebhookController extends Controller
{
    public function __construct(
        private readonly WebhookIdempotencyInterface $idempotencyService
    ) {}

    public function ipn(Request $request): JsonResponse
    {
        $payload = $request->all();
        $tranId = (string) ($payload['tran_id'] ?? '');
        $valId = (string) ($payload['val_id'] ?? $tranId);
        $status = (string) ($payload['status'] ?? '');
        $amount = (float) ($payload['amount'] ?? 0.0);
        $valA = ! empty($payload['value_a']) ? (string) $payload['value_a'] : null;
        $valB = ! empty($payload['value_b']) ? (string) $payload['value_b'] : null;
        $verifySign = ! empty($payload['verify_sign']) ? (string) $payload['verify_sign'] : null;

        if ($tranId === '' || ! in_array($status, ['VALID', 'VALIDATED'], true)) {
            return response()->json([
                'status' => 'ignored',
                'message' => 'Transaction not completed or missing tran_id',
            ], 400);
        }

        // 1. Acquire Redis Idempotency Lock
        $locked = $this->idempotencyService->verifyAndLock('sslcommerz', $tranId, $payload, $verifySign);
        if (! $locked) {
            return response()->json([
                'status' => 'duplicate_ignored',
                'message' => 'Transaction already processed or concurrent IPN received',
            ], 409);
        }

        // 2. Resolve invoice_id and subscriber_id (support value_a as either invoice or subscriber)
        $invoiceId = null;
        $subscriberId = null;

        if ($valA) {
            $invCheck = DB::table('invoices')->where('invoice_id', $valA)->first();
            if ($invCheck) {
                $invoiceId = $valA;
                $subscriberId = $valB ?: (string) $invCheck->subscriber_id;
            } else {
                $subscriberId = $valA;
                $invoiceId = $valB;
            }
        } elseif ($valB) {
            $invCheck = DB::table('invoices')->where('invoice_id', $valB)->first();
            if ($invCheck) {
                $invoiceId = $valB;
                $subscriberId = (string) $invCheck->subscriber_id;
            }
        }

        // 3. Database transaction to record log, payment, and immediate settlement
        $tenantId = 1;
        DB::transaction(function () use ($tranId, $payload, $subscriberId, $invoiceId, $amount, $tenantId): void {
            $this->idempotencyService->recordSuccess(
                'sslcommerz',
                $tranId,
                $payload,
                $subscriberId,
                $tenantId
            );

            if ($subscriberId && $invoiceId) {
                DB::table('payments')->insert([
                    'tenant_id' => $tenantId,
                    'subscriber_id' => $subscriberId,
                    'invoice_id' => $invoiceId,
                    'amount_bdt' => $amount,
                    'gateway' => 'sslcommerz',
                    'trx_id' => $tranId,
                    'status' => 'completed',
                    'paid_at' => now(),
                    'created_at' => now(),
                ]);

                DB::table('invoices')
                    ->where('invoice_id', $invoiceId)
                    ->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                    ]);

                DB::table('subscribers')
                    ->where('subscriber_id', $subscriberId)
                    ->update([
                        'status' => 'active',
                        'dunning_state' => 'active',
                        'expires_at' => now()->addDays(30),
                        'grace_until' => null,
                        'suspended_at' => null,
                    ]);
            }
        });

        // 4. Dispatch instant (<3s SLA) subscriber unblock on critical queue
        if ($subscriberId && $invoiceId) {
            RestoreSubscriberOnPaymentJob::dispatch(
                $subscriberId,
                $invoiceId,
                'sslcommerz',
                $amount,
                $tenantId
            )->onQueue('critical');
        }

        Log::info("SSLCOMMERZ IPN processed successfully for tran_id: {$tranId}, val_id: {$valId}");

        return response()->json([
            'status' => 'success',
            'message' => 'IPN processed and unblock dispatched',
            'tran_id' => $tranId,
        ]);
    }
}
