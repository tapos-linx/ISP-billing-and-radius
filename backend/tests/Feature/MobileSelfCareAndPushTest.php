<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Subscriber;
use App\Models\Tariff;
use App\Services\Mobile\MobilePushService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Release 2.2: Subscriber Self-Care Mobile Application & Push Gateway Feature Tests
 */
class MobileSelfCareAndPushTest extends TestCase
{
    use DatabaseTransactions;

    protected Subscriber $subscriber;

    protected Tariff $tariff;

    protected function setUp(): void
    {
        parent::setUp();

        $tenantId = 1;

        $this->tariff = Tariff::withoutGlobalScopes()->where('tenant_id', $tenantId)->first() ?? Tariff::create([
            'tenant_id' => $tenantId,
            'name' => 'Mobile Fiber 30M',
            'rate_limit' => '30M/30M',
            'price_bdt' => 1200.00,
            'validity_days' => 30,
            'created_at' => Carbon::now(),
        ]);

        $subId = (string) Str::uuid();
        $this->subscriber = Subscriber::withoutGlobalScopes()->create([
            'subscriber_id' => $subId,
            'tenant_id' => $tenantId,
            'username' => 'mobile_sub_'.Str::lower(Str::random(5)),
            'password' => 'Pass@'.Str::random(6),
            'tariff_id' => $this->tariff->tariff_id ?? $this->tariff->id,
            'bandwidth_profile' => '30M/30M',
            'status' => 'active',
            'expires_at' => Carbon::now()->addDays(20),
            'auto_pay_enabled' => false,
            'pro_rata_balance' => 0.0,
        ]);
    }

    public function test_mobile_profile_endpoint_returns_accurate_telemetry(): void
    {
        $response = $this->getJson("/api/v1/mobile/profile?subscriber_id={$this->subscriber->subscriber_id}");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'subscriber_id' => $this->subscriber->subscriber_id,
                'username' => $this->subscriber->username,
                'status' => 'active',
                'bandwidth_profile' => '30M/30M',
            ],
        ]);

        $this->assertGreaterThan(0, $response->json('data.days_remaining'));
        $this->assertNotNull($response->json('data.tariff'));
        $this->assertEquals('ONLINE', $response->json('data.network.service_status'));
    }

    public function test_mobile_bandwidth_usage_returns_fup_quota_progress(): void
    {
        $response = $this->getJson("/api/v1/mobile/usage?subscriber_id={$this->subscriber->subscriber_id}");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'subscriber_id' => $this->subscriber->subscriber_id,
            ],
        ]);

        $this->assertGreaterThan(0, $response->json('data.total_limit_bytes'));
        $this->assertGreaterThanOrEqual(0, $response->json('data.percent_used'));
        $this->assertCount(7, $response->json('data.daily_trend'));
    }

    public function test_mobile_ont_diagnostics_reports_optical_power(): void
    {
        $response = $this->postJson('/api/v1/mobile/diagnose', [
            'subscriber_id' => $this->subscriber->subscriber_id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'subscriber_id' => $this->subscriber->subscriber_id,
                'optical_status' => 'OPTIMAL',
            ],
        ]);

        $this->assertLessThan(-8.0, $response->json('data.optical_rx_power_dbm'));
        $this->assertGreaterThan(-27.0, $response->json('data.optical_rx_power_dbm'));
        $this->assertNotNull($response->json('data.wifi_status.ssid_24ghz'));
    }

    public function test_mobile_speed_boost_applies_coa_kick(): void
    {
        $response = $this->postJson('/api/v1/mobile/boost', [
            'subscriber_id' => $this->subscriber->subscriber_id,
            'boost_speed_mbps' => 100,
            'duration_hours' => 6,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'success' => true,
                'boost_rate_limit' => '100M/100M',
                'duration_hours' => 6,
            ],
        ]);

        // Verify subscriber model updated
        $this->subscriber->refresh();
        $this->assertEquals('100M/100M', $this->subscriber->bandwidth_profile);
    }

    public function test_mobile_device_registration_and_push_notification_dispatch(): void
    {
        $deviceUuid = 'DEV-'.Str::upper(Str::random(12));
        $fcmToken = 'fcm_token_'.Str::random(40);

        // 1. Register Device
        $regResponse = $this->postJson('/api/v1/mobile/devices/register', [
            'subscriber_id' => $this->subscriber->subscriber_id,
            'tenant_id' => 1,
            'device_uuid' => $deviceUuid,
            'platform' => 'android',
            'fcm_token' => $fcmToken,
            'device_name' => 'Pixel 8 Pro',
            'app_version' => '2.2.0',
        ]);

        $regResponse->assertStatus(201);
        $this->assertEquals($deviceUuid, $regResponse->json('device.device_uuid'));

        // 2. Dispatch push notification via service
        /** @var MobilePushService $pushService */
        $pushService = app(MobilePushService::class);
        $dispatchResult = $pushService->sendToSubscriber(
            $this->subscriber->subscriber_id,
            1,
            'billing',
            'Monthly Invoice Generated',
            'Your Mushak-6.3 invoice for November 2026 is ready.',
            ['invoice_id' => (string) Str::uuid()]
        );

        $this->assertTrue($dispatchResult['success']);
        $this->assertEquals(1, $dispatchResult['dispatched_count']);

        // 3. Query notification history
        $notifResponse = $this->getJson("/api/v1/mobile/notifications?subscriber_id={$this->subscriber->subscriber_id}");
        $notifResponse->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $notifResponse->json('count'));
        $this->assertEquals('billing', $notifResponse->json('data.0.channel'));
    }
}
