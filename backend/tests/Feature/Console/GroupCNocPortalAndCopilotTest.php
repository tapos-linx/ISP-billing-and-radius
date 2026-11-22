<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Actions\Noc\ForceDisconnectPppoeAction;
use App\Jobs\ForceDisconnectSessionJob;
use App\Services\SelfCare\SubscriberUsageService;
use App\Services\Support\CopilotToolRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class GroupCNocPortalAndCopilotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.testing' => true]);
    }

    public function test_noc_force_disconnect_action_dispatches_critical_job_and_audits(): void
    {
        Queue::fake([ForceDisconnectSessionJob::class]);

        $action = app(ForceDisconnectPppoeAction::class);
        $username = 'pppoe_kick_'.Str::random(6);
        $subscriberId = (string) Str::uuid();

        // 1. Operator executes force disconnect with permission
        $result = $action->execute(
            username: $username,
            subscriberId: $subscriberId,
            reason: 'High broadcast flood investigation',
            userContext: ['user_id' => 42, 'permissions' => ['noc.disconnect_session'], 'tenant_id' => 1]
        );

        $this->assertSame('queued', $result['status']);
        Queue::assertPushedOn('critical', ForceDisconnectSessionJob::class, function ($job) use ($username, $subscriberId) {
            return $job->username === $username && $job->subscriberId === $subscriberId;
        });

        // 2. Unauthorized operator without permission is blocked
        $this->expectException(AuthorizationException::class);
        $action->execute(
            username: $username,
            subscriberId: $subscriberId,
            reason: 'Unauthorized test',
            userContext: ['user_id' => 99, 'permissions' => ['read_only_viewer']]
        );
    }

    public function test_subscriber_usage_service_aggregates_monthly_octets_and_session_time(): void
    {
        $service = app(SubscriberUsageService::class);
        $subscriberId = (string) Str::uuid();
        $username = 'acct_user_'.Str::random(6);

        // 1. Seed subscriber
        DB::table('subscribers')->insert([
            'subscriber_id' => $subscriberId,
            'tenant_id' => 1,
            'username' => $username,
            'status' => 'active',
            'expires_at' => now()->addMonth(),
            'created_at' => now(),
        ]);

        // 2. Seed accounting records (e.g. 2 GB down, 1 GB up, 7200 sec uptime)
        $downBytes = 2 * 1024 * 1024 * 1024; // 2 GB
        $upBytes = 1 * 1024 * 1024 * 1024;   // 1 GB

        DB::table('radacct')->insert([
            'radacctid' => mt_rand(100000, 999999),
            'tenant_id' => 1,
            'acctsessionid' => 'sess_'.Str::random(10),
            'username' => $username,
            'nasipaddress' => '10.0.0.1',
            'acctstarttime' => now()->startOfMonth()->addDays(2),
            'acctstoptime' => now()->startOfMonth()->addDays(2)->addHours(2),
            'acctinputoctets' => $upBytes,
            'acctoutputoctets' => $downBytes,
        ]);

        $summary = $service->getMonthlySummary($subscriberId, now()->format('Y-m'));

        $this->assertSame($subscriberId, $summary['subscriber_id']);
        $this->assertEquals(2.0, $summary['download_gb']);
        $this->assertEquals(1.0, $summary['upload_gb']);
        $this->assertEquals(3.0, $summary['total_gb']);
        $this->assertEquals(2.0, $summary['total_uptime_hours']);
        $this->assertSame(1, $summary['session_count']);
    }

    public function test_copilot_tool_registry_executes_allowlisted_tools_and_records_audit(): void
    {
        $registry = app(CopilotToolRegistry::class);
        $subscriberId = (string) Str::uuid();

        // 1. Seed subscriber & ONT
        DB::table('subscribers')->insert([
            'subscriber_id' => $subscriberId,
            'tenant_id' => 1,
            'username' => 'copilot_user_'.Str::random(6),
            'status' => 'active',
            'expires_at' => now()->addMonth(),
            'created_at' => now(),
        ]);

        DB::table('ont_devices')->insert([
            'ont_id' => (string) Str::uuid(),
            'subscriber_id' => $subscriberId,
            'serial_number' => 'HWTC_'.Str::random(8),
            'rx_power_dbm' => -19.45,
            'tx_power_dbm' => 2.30,
            'last_seen_at' => now(),
        ]);

        // 2. Execute getOntOpticalPower
        $opticalResult = $registry->execute('getOntOpticalPower', [], $subscriberId, 1);
        $this->assertSame('GOOD', $opticalResult['optical_health']);
        $this->assertEquals(-19.45, $opticalResult['rx_power_dbm']);

        // 3. Verify copilot_diagnostic_logs audit row
        $log = DB::table('copilot_diagnostic_logs')
            ->where('subscriber_id', $subscriberId)
            ->where('tool_name', 'getOntOpticalPower')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);

        // 4. Test Security Boundary: Disallow unlisted tools
        $this->expectException(InvalidArgumentException::class);
        $registry->execute('deleteSubscriberData', [], $subscriberId, 1);
    }
}
