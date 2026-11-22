<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Filament\Widgets\AiCopilotDrawerWidget;
use App\Filament\Widgets\NocTelemetryStripWidget;
use App\Services\Support\CopilotToolRegistry;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FiberPrismNocAndPortalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.testing' => true,
            'inertia.pages.paths' => [
                resource_path('js/Pages'),
                resource_path('js/pages'),
            ],
        ]);
    }

    public function test_noc_telemetry_strip_widget_aggregates_live_citus_and_queue_metrics(): void
    {
        $uniqueUser = 'pppoe_test_'.Str::random(6);
        $uniqueSubId = (string) Str::uuid();

        // Seed an active radacct session
        DB::table('radacct')->insert([
            'acctsessionid' => Str::random(12),
            'username' => $uniqueUser,
            'realm' => 'broadband',
            'nasipaddress' => '10.0.0.1',
            'acctstarttime' => now()->subHours(2),
            'acctstoptime' => null,
            'acctinputoctets' => 104857600,
            'acctoutputoctets' => 524288000,
            'tenant_id' => 1,
        ]);

        // Seed a critical optical Rx ONT device (< -27.0 dBm)
        DB::table('ont_devices')->insert([
            'ont_id' => (string) Str::uuid(),
            'subscriber_id' => $uniqueSubId,
            'serial_number' => 'ALCL'.Str::random(8),
            'olt_pon_port' => 'gpon-olt-1/1/1',
            'rx_power_dbm' => -29.40,
            'tx_power_dbm' => 2.10,
            'last_seen_at' => now(),
        ]);

        $widget = new class extends NocTelemetryStripWidget
        {
            /**
             * @return array<int, Stat>
             */
            public function getPublicStats(): array
            {
                return $this->getStats();
            }
        };

        $stats = $widget->getPublicStats();

        $this->assertCount(4, $stats);
        $this->assertSame('Active PPPoE Sessions', $stats[0]->getLabel());
        $this->assertGreaterThanOrEqual(1, (int) $stats[0]->getValue());

        $this->assertSame('RADIUS Auth Latency', $stats[1]->getLabel());
        $this->assertSame('<10ms', $stats[1]->getValue());

        $this->assertSame('Critical ONT / LOS Alerts', $stats[2]->getLabel());
        $this->assertGreaterThanOrEqual(1, (int) $stats[2]->getValue());

        $this->assertSame('Critical Queue Depth', $stats[3]->getLabel());
        $this->assertIsNumeric($stats[3]->getValue());
    }

    public function test_ai_copilot_drawer_widget_executes_allowlisted_tools_and_rejects_unauthorized_tools(): void
    {
        $subscriberId = (string) Str::uuid();
        $username = 'copilot_ui_'.Str::random(6);

        DB::table('subscribers')->insert([
            'subscriber_id' => $subscriberId,
            'tenant_id' => 1,
            'tariff_id' => 1,
            'username' => $username,
            'status' => 'active',
            'dunning_state' => 'active',
            'bandwidth_profile' => '50M/50M Fiber Fast',
            'expires_at' => now()->addDays(15),
            'created_at' => now(),
        ]);

        $registry = app(CopilotToolRegistry::class);
        $widget = new AiCopilotDrawerWidget;

        // 1. Success on allowlisted tool
        $widget->subscriberQuery = $subscriberId;
        $widget->selectedTool = 'getSubscriberRadiusState';
        $widget->runDiagnostics($registry);

        $this->assertNull($widget->errorMessage);
        $this->assertIsArray($widget->diagnosticResult);
        $this->assertSame($subscriberId, $widget->diagnosticResult['subscriber_id']);
        $this->assertSame('active', $widget->diagnosticResult['dunning_state']);

        // 2. Security boundary rejection on non-allowlisted tool
        $widget->selectedTool = 'dropTableSubscribers';
        $widget->runDiagnostics($registry);

        $this->assertNotNull($widget->errorMessage);
        $this->assertStringContainsString('not allowed or violates read-only copilot security boundaries', $widget->errorMessage);
        $this->assertNull($widget->diagnosticResult);

        // 3. Validation failure on empty query
        $widget->subscriberQuery = '';
        $widget->runDiagnostics($registry);

        $this->assertNotNull($widget->errorMessage);
        $this->assertStringContainsString('Subscriber ID or Username is required', $widget->errorMessage);

        // 4. Reset diagnostics cleans state
        $widget->resetDiagnostics();
        $this->assertNull($widget->diagnosticResult);
        $this->assertNull($widget->errorMessage);
        $this->assertSame('', $widget->subscriberQuery);
    }

    public function test_optical_power_bar_component_renders_spectrum_and_status_badges(): void
    {
        // 1. Optimal light (-19.4 dBm)
        $htmlOptimal = view('filament.components.optical-power-bar', ['rxPower' => -19.4])->render();
        $this->assertStringContainsString('data-testid="optical-power-spectrum-bar"', $htmlOptimal);
        $this->assertStringContainsString('-19.4 dBm', $htmlOptimal);
        $this->assertStringContainsString('Optimal', $htmlOptimal);

        // 2. Degraded light (-26.1 dBm)
        $htmlDegraded = view('filament.components.optical-power-bar', ['rxPower' => -26.1])->render();
        $this->assertStringContainsString('-26.1 dBm', $htmlDegraded);
        $this->assertStringContainsString('Degraded', $htmlDegraded);

        // 3. Critical LOS light (-31.2 dBm)
        $htmlCritical = view('filament.components.optical-power-bar', ['rxPower' => -31.2])->render();
        $this->assertStringContainsString('-31.2 dBm', $htmlCritical);
        $this->assertStringContainsString('LOS / Critical', $htmlCritical);
    }

    public function test_portal_status_api_endpoint_returns_accurate_telemetry_and_usage(): void
    {
        $subscriberId = (string) Str::uuid();
        $username = 'portal_api_'.Str::random(6);

        DB::table('subscribers')->insert([
            'subscriber_id' => $subscriberId,
            'tenant_id' => 1,
            'tariff_id' => 1,
            'username' => $username,
            'status' => 'expired',
            'dunning_state' => 'walled_garden',
            'bandwidth_profile' => '100M/100M Fiber Turbo',
            'expires_at' => now()->subDay(),
            'created_at' => now()->subMonths(2),
        ]);

        // Seed radacct session to test usage metrics
        DB::table('radacct')->insert([
            'acctsessionid' => Str::random(12),
            'username' => $username,
            'realm' => 'broadband',
            'nasipaddress' => '10.10.10.1',
            'acctstarttime' => now()->startOfMonth()->addHours(1),
            'acctstoptime' => now()->startOfMonth()->addHours(5),
            'acctinputoctets' => 5368709120,   // ~5 GB upload
            'acctoutputoctets' => 21474836480, // ~20 GB download
            'tenant_id' => 1,
        ]);

        $response = $this->getJson("/api/v1/portal/status?subscriber_id={$subscriberId}");

        $response->assertOk()
            ->assertJsonPath('subscriber_id', $subscriberId)
            ->assertJsonPath('username', $username)
            ->assertJsonPath('status', 'expired')
            ->assertJsonPath('dunning_state', 'walled_garden')
            ->assertJsonPath('speed_tier', '100M/100M Fiber Turbo')
            ->assertJsonStructure([
                'subscriber_id',
                'username',
                'status',
                'dunning_state',
                'speed_tier',
                'is_online',
                'active_ip',
                'expires_at',
                'days_remaining',
                'optical_rx_dbm',
                'usage' => [
                    'download_bytes',
                    'upload_bytes',
                    'total_bytes',
                    'download_gb',
                    'upload_gb',
                    'total_gb',
                    'uptime_hours',
                    'billing_period',
                ],
                'timestamp',
            ]);
    }

    public function test_portal_dashboard_web_route_renders_inertia_page(): void
    {
        $subscriberId = (string) Str::uuid();
        $username = 'portal_web_'.Str::random(6);

        DB::table('subscribers')->insert([
            'subscriber_id' => $subscriberId,
            'tenant_id' => 1,
            'tariff_id' => 1,
            'username' => $username,
            'status' => 'active',
            'dunning_state' => 'active',
            'bandwidth_profile' => '50M/50M Fiber Fast',
            'expires_at' => now()->addDays(20),
            'created_at' => now(),
        ]);

        $response = $this->get("/portal/dashboard?subscriber_id={$subscriberId}");

        $response->assertOk();
        $response->assertInertia(function (Assert $page) use ($subscriberId, $username) {
            $page->component('Portal/SubscriberDashboard')
                ->where('initialData.subscriber_id', $subscriberId)
                ->where('initialData.username', $username)
                ->where('initialData.status', 'active');
        });
    }
}
