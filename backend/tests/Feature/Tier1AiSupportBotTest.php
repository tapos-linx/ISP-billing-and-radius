<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\NetworkAlarm;
use App\Models\Onu;
use App\Models\Subscriber;
use App\Services\Radius\RadiusCoaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class Tier1AiSupportBotTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_slow_internet_query_returns_optical_signal_and_active_session(): void
    {
        $subId = (string) Str::uuid();
        $oltId = (string) Str::uuid();
        $onuId = (string) Str::uuid();

        $subscriber = Subscriber::withoutGlobalScopes()->create([
            'subscriber_id' => $subId,
            'tenant_id' => 1,
            'username' => 'test_ai_'.Str::lower(Str::random(6)),
            'status' => 'active',
            'bandwidth_profile' => '25M/25M',
        ]);

        Onu::withoutGlobalScopes()->create([
            'onu_id' => $onuId,
            'tenant_id' => 1,
            'olt_id' => $oltId,
            'subscriber_id' => $subscriber->subscriber_id,
            'pon_port' => '1/1/1',
            'onu_index' => 1,
            'vlan_id' => 100,
            'serial_number' => 'HWTC'.Str::random(8),
            'status' => 'online',
            'rx_power_dbm' => -20.50,
            'tx_power_dbm' => 2.15,
            'distance_m' => 420,
        ]);

        $response = $this->postJson('/api/v1/support/bot/query', [
            'query' => 'My internet is very slow and buffering on youtube',
            'subscriber_id' => $subscriber->subscriber_id,
            'channel' => 'web_copilot',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.intent', 'slow_internet')
            ->assertJsonPath('data.diagnostics.rx_power_dbm', -20.5)
            ->assertJsonPath('data.diagnostics.health_status', 'OPTIMAL');

        $this->assertStringContainsString('Fiber Health & Session Report', $response->json('data.reply'));
    }

    public function test_critical_low_light_auto_generates_trouble_ticket(): void
    {
        $subId = (string) Str::uuid();
        $oltId = (string) Str::uuid();
        $onuId = (string) Str::uuid();

        $subscriber = Subscriber::withoutGlobalScopes()->create([
            'subscriber_id' => $subId,
            'tenant_id' => 1,
            'username' => 'test_light_'.Str::lower(Str::random(6)),
            'status' => 'active',
            'bandwidth_profile' => '20M/20M',
        ]);

        Onu::withoutGlobalScopes()->create([
            'onu_id' => $onuId,
            'tenant_id' => 1,
            'olt_id' => $oltId,
            'subscriber_id' => $subscriber->subscriber_id,
            'pon_port' => '1/1/2',
            'onu_index' => 2,
            'vlan_id' => 100,
            'serial_number' => 'HWTC'.Str::random(8),
            'status' => 'online',
            'rx_power_dbm' => -29.80, // Critical Low Light
            'distance_m' => 850,
        ]);

        $response = $this->postJson('/api/v1/support/bot/query', [
            'query' => 'Why is my optical signal light red?',
            'subscriber_id' => $subscriber->subscriber_id,
            'channel' => 'web_copilot',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.diagnostics.health_status', 'CRITICAL_LOW_LIGHT')
            ->assertJsonPath('data.diagnostics.action_taken', 'DISPATCH_FIELD_TECH');

        $this->assertDatabaseHas('tickets', [
            'subscriber_id' => $subscriber->subscriber_id,
            'category' => 'optical_fiber',
            'priority' => 'high',
        ]);
    }

    public function test_regional_outage_inquiry_identifies_active_network_alarm(): void
    {
        NetworkAlarm::withoutGlobalScopes()->create([
            'tenant_id' => 1,
            'device_id' => (string) Str::uuid(),
            'alarm_type' => 'FIBER_CUT_TRUNK_RING_NORTH',
            'severity' => 'critical',
            'status' => 'active',
            'detected_at' => now(),
        ]);

        $subId = (string) Str::uuid();
        $subscriber = Subscriber::withoutGlobalScopes()->create([
            'subscriber_id' => $subId,
            'tenant_id' => 1,
            'username' => 'test_outage_'.Str::lower(Str::random(6)),
            'status' => 'active',
            'bandwidth_profile' => '20M/20M',
        ]);

        $response = $this->postJson('/api/v1/support/bot/query', [
            'query' => 'Is there any area outage or fiber cut right now?',
            'subscriber_id' => $subscriber->subscriber_id,
            'channel' => 'whatsapp',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'outage_inquiry')
            ->assertJsonPath('data.diagnostics.outage_detected', true);

        $this->assertStringContainsString('AREA OUTAGE ALERT', $response->json('data.reply'));
    }

    public function test_billing_inquiry_returns_due_amount_with_vat_and_bkash_link(): void
    {
        $subId = (string) Str::uuid();
        $subscriber = Subscriber::withoutGlobalScopes()->create([
            'subscriber_id' => $subId,
            'tenant_id' => 1,
            'username' => 'test_bill_'.Str::lower(Str::random(6)),
            'status' => 'active',
            'bandwidth_profile' => '30M/30M',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'tenant_id' => 1,
            'subscriber_id' => $subscriber->subscriber_id,
            'amount_bdt' => 1050.00,
            'subtotal_bdt' => 1000.00,
            'vat_rate_percent' => 5.00,
            'vat_amount_bdt' => 50.00,
            'total_bdt' => 1050.00,
            'status' => 'unpaid',
            'mushak_6_3_number' => 'MUSHAK-6.3-2026-9901',
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $response = $this->postJson('/api/v1/support/bot/query', [
            'query' => 'How much is my pending bill for this month?',
            'subscriber_id' => $subscriber->subscriber_id,
            'channel' => 'whatsapp',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'bill_inquiry')
            ->assertJsonPath('data.diagnostics.has_due', true)
            ->assertJsonPath('data.diagnostics.due_amount_bdt', 1050);

        $this->assertStringContainsString('pay.carrier.net/bkash/checkout', (string) $response->json('data.payment_url'));
        $this->assertStringContainsString('NBR 5% Telecom VAT', $response->json('data.reply'));
    }

    public function test_speed_boost_request_invokes_dynamic_coa(): void
    {
        $subId = (string) Str::uuid();
        $username = 'test_boost_'.Str::lower(Str::random(6));
        $subscriber = Subscriber::withoutGlobalScopes()->create([
            'subscriber_id' => $subId,
            'tenant_id' => 1,
            'username' => $username,
            'status' => 'active',
            'bandwidth_profile' => '20M/20M',
        ]);

        $mockCoa = Mockery::mock(RadiusCoaService::class);
        $mockCoa->shouldReceive('changeBandwidthForUser')
            ->once()
            ->with($username, '50M/50M', $subId)
            ->andReturn(true);
        $this->app->instance(RadiusCoaService::class, $mockCoa);

        $response = $this->postJson('/api/v1/support/bot/query', [
            'query' => 'I want to boost my speed for downloading game',
            'subscriber_id' => $subId,
            'channel' => 'web_copilot',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'speed_boost')
            ->assertJsonPath('data.action_taken', 'COA_BOOST_DISPATCHED')
            ->assertJsonPath('data.diagnostics.boost_rate', '50M/50M');
    }

    public function test_whatsapp_webhook_integration_flow(): void
    {
        $subId = (string) Str::uuid();
        $username = 'test_wa_'.Str::lower(Str::random(6));
        $subscriber = Subscriber::withoutGlobalScopes()->create([
            'subscriber_id' => $subId,
            'tenant_id' => 1,
            'username' => $username,
            'status' => 'active',
            'bandwidth_profile' => '15M/15M',
        ]);

        $webhookPayload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'from' => $username,
                                        'text' => [
                                            'body' => 'slow internet connection',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/support/bot/webhook', $webhookPayload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.intent', 'slow_internet');
    }
}
