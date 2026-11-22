<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Billing\DunningStateMachineInterface;
use App\Jobs\DispatchCoaDisconnectJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DunningStateMachine implements DunningStateMachineInterface
{
    private const VALID_STATES = ['active', 'grace', 'expired', 'suspended'];

    public function transition(string $subscriberId, string $targetState, ?string $reason = null): bool
    {
        if (! in_array($targetState, self::VALID_STATES, true)) {
            throw new InvalidArgumentException("Invalid dunning state: {$targetState}");
        }

        $subscriber = DB::table('subscribers')
            ->where('subscriber_id', $subscriberId)
            ->first();

        if (! $subscriber) {
            return false;
        }

        $now = now();
        $updateData = [
            'dunning_state' => $targetState,
            'status' => $targetState,
        ];

        if ($targetState === 'suspended') {
            $updateData['suspended_at'] = $now;
        } elseif ($targetState === 'active') {
            $updateData['suspended_at'] = null;
        }

        DB::table('subscribers')
            ->where('subscriber_id', $subscriberId)
            ->update($updateData);

        // 1. Update FreeRADIUS radreply walled garden pool
        if (in_array($targetState, ['expired', 'suspended'], true)) {
            // Apply walled garden pool in radreply
            DB::table('radreply')->updateOrInsert(
                [
                    'subscriber_id' => $subscriberId,
                    'username' => $subscriber->username,
                    'attribute' => 'Framed-Pool',
                ],
                [
                    'op' => ':=',
                    'value' => 'walled_garden_pool',
                ]
            );

            // Disconnect current session via critical queue
            DispatchCoaDisconnectJob::dispatch(
                $subscriber->username,
                $subscriberId,
                (int) $subscriber->tenant_id
            )->onQueue('critical');
        } elseif ($targetState === 'active') {
            // Remove walled garden pool
            DB::table('radreply')
                ->where('username', $subscriber->username)
                ->where('attribute', 'Framed-Pool')
                ->where('value', 'walled_garden_pool')
                ->delete();

            // Kick session to re-auth on high-speed profile
            DispatchCoaDisconnectJob::dispatch(
                $subscriber->username,
                $subscriberId,
                (int) $subscriber->tenant_id
            )->onQueue('critical');
        }

        // 2. Audit in dunning_events
        $activeInvoice = DB::table('invoices')
            ->where('subscriber_id', $subscriberId)
            ->where('status', 'unpaid')
            ->latest('created_at')
            ->first();

        $invoiceId = $activeInvoice ? (string) $activeInvoice->invoice_id : (string) Str::uuid();

        DB::table('dunning_events')->insert([
            'tenant_id' => (int) $subscriber->tenant_id,
            'subscriber_id' => $subscriberId,
            'invoice_id' => $invoiceId,
            'stage' => $targetState,
            'fee_assessed_bdt' => 0.00,
            'notes' => $reason ?? "Transitioned to {$targetState}",
            'created_at' => $now,
        ]);

        return true;
    }

    /**
     * @return array<string, int>
     */
    public function processDunningLifecycle(): array
    {
        $now = now();
        $counts = ['grace' => 0, 'expired' => 0, 'suspended' => 0];

        // 1. Active subscribers past expiration but with valid grace_until -> 'grace'
        $toGrace = DB::table('subscribers')
            ->where('status', 'active')
            ->where('expires_at', '<', $now)
            ->where('grace_until', '>', $now)
            ->get();

        foreach ($toGrace as $sub) {
            $this->transition((string) $sub->subscriber_id, 'grace', 'Subscription expired; moved to grace period');
            $counts['grace']++;
        }

        // 2. Subscribers past expiration and past grace -> 'expired'
        $toExpire = DB::table('subscribers')
            ->whereIn('status', ['active', 'grace'])
            ->where('expires_at', '<', $now)
            ->where(function ($q) use ($now): void {
                $q->whereNull('grace_until')
                    ->orWhere('grace_until', '<=', $now);
            })
            ->get();

        foreach ($toExpire as $sub) {
            $this->transition((string) $sub->subscriber_id, 'expired', 'Subscription expired and grace period lapsed');
            $counts['expired']++;
        }

        return $counts;
    }
}
