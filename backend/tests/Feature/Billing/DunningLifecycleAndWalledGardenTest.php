<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Contracts\Billing\DunningStateMachineInterface;
use App\Jobs\DispatchCoaDisconnectJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class DunningLifecycleAndWalledGardenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.testing' => true]);
    }

    public function test_dunning_transition_to_expired_applies_walled_garden_and_kicks_session(): void
    {
        Queue::fake([DispatchCoaDisconnectJob::class]);

        $stateMachine = app(DunningStateMachineInterface::class);
        $subscriberId = (string) Str::uuid();
        $username = 'dunning_user_'.Str::random(6);

        // 1. Seed subscriber
        DB::table('subscribers')->insert([
            'subscriber_id' => $subscriberId,
            'tenant_id' => 1,
            'username' => $username,
            'status' => 'active',
            'dunning_state' => 'active',
            'expires_at' => now()->subDay(),
            'created_at' => now(),
        ]);

        // 2. Transition to 'expired'
        $success = $stateMachine->transition($subscriberId, 'expired', 'Monthly subscription bill overdue');
        $this->assertTrue($success);

        // 3. Verify radreply has Framed-Pool := walled_garden_pool
        $reply = DB::table('radreply')
            ->where('username', $username)
            ->where('attribute', 'Framed-Pool')
            ->first();

        $this->assertNotNull($reply);
        $this->assertSame('walled_garden_pool', $reply->value);
        $this->assertSame(':=', $reply->op);

        // 4. Verify DispatchCoaDisconnectJob was pushed on 'critical' queue
        Queue::assertPushedOn('critical', DispatchCoaDisconnectJob::class, function ($job) use ($username, $subscriberId) {
            return $job->username === $username && $job->subscriberId === $subscriberId;
        });

        // 5. Verify dunning_events audit row
        $event = DB::table('dunning_events')
            ->where('subscriber_id', $subscriberId)
            ->where('stage', 'expired')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('Monthly subscription bill overdue', $event->notes);
    }

    public function test_dunning_transition_to_active_removes_walled_garden_pool(): void
    {
        Queue::fake([DispatchCoaDisconnectJob::class]);

        $stateMachine = app(DunningStateMachineInterface::class);
        $subscriberId = (string) Str::uuid();
        $username = 'restored_user_'.Str::random(6);

        DB::table('subscribers')->insert([
            'subscriber_id' => $subscriberId,
            'tenant_id' => 1,
            'username' => $username,
            'status' => 'expired',
            'dunning_state' => 'expired',
            'expires_at' => now()->subDays(5),
            'created_at' => now(),
        ]);

        // Insert walled garden pool entry
        DB::table('radreply')->insert([
            'subscriber_id' => $subscriberId,
            'username' => $username,
            'attribute' => 'Framed-Pool',
            'op' => ':=',
            'value' => 'walled_garden_pool',
        ]);

        // Transition back to 'active' on payment
        $success = $stateMachine->transition($subscriberId, 'active', 'Payment received via bKash');
        $this->assertTrue($success);

        // Verify walled garden entry removed
        $reply = DB::table('radreply')
            ->where('username', $username)
            ->where('attribute', 'Framed-Pool')
            ->first();

        $this->assertNull($reply);

        // Verify subscriber record is active
        $subscriber = DB::table('subscribers')->where('subscriber_id', $subscriberId)->first();
        $this->assertNotNull($subscriber);
        $this->assertSame('active', $subscriber->status);
        $this->assertSame('active', $subscriber->dunning_state);

        // Verify session kick dispatched on 'critical' queue
        Queue::assertPushedOn('critical', DispatchCoaDisconnectJob::class);
    }
}
