<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Contracts\Payments\WebhookIdempotencyInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class WebhookIdempotencyService implements WebhookIdempotencyInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyAndLock(
        string $gateway,
        string $idempotencyKey,
        array $payload,
        ?string $signature = null
    ): bool {
        // 1. Signature validation if secret is configured and signature provided
        $secret = (string) config("services.{$gateway}.webhook_secret", config("services.{$gateway}.app_secret", ''));
        if ($secret !== '' && $signature !== null) {
            $expected = hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), $secret);
            if (! hash_equals($expected, $signature)) {
                return false;
            }
        }

        // 2. Atomic Redis SET lockKey 1 EX 86400 NX
        $lockKey = "webhook_idempotency:{$gateway}:{$idempotencyKey}";

        try {
            /** @var mixed $acquired */
            $acquired = Redis::set($lockKey, '1', 'EX', 86400, 'NX');

            return (bool) $acquired;
        } catch (\Throwable) {
            // Fallback to database uniqueness if Redis unavailable
            return ! DB::table('payment_webhook_logs')
                ->where('gateway', $gateway)
                ->where('idempotency_key', $idempotencyKey)
                ->exists();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordSuccess(
        string $gateway,
        string $idempotencyKey,
        array $payload,
        ?string $subscriberId = null,
        int $tenantId = 1
    ): void {
        $encoded = json_encode($payload);
        $payloadHash = hash('sha256', $encoded !== false ? $encoded : '');

        DB::table('payment_webhook_logs')->updateOrInsert(
            [
                'gateway' => $gateway,
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'tenant_id' => $tenantId,
                'subscriber_id' => $subscriberId,
                'payload_hash' => $payloadHash,
                'payload' => $encoded !== false ? $encoded : '{}',
                'status' => 'processed',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function verifyHmacSignature(string $payloadRaw, string $signature, string $secret): bool
    {
        $computed = hash_hmac('sha256', $payloadRaw, $secret);

        return hash_equals($computed, $signature);
    }
}
