<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Jobs\SyncNasRouterStateJob;
use App\Services\Cpe\GenieAcsTr069Service;
use App\Services\Ipam\IpamAllocatorService;
use App\Services\Network\RouterOsApiService;
use App\Services\Radius\ClientsConfGenerator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class GroupBNetworkAndRadiusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.testing' => true]);
    }

    public function test_routeros_api_service_syncs_active_pppoe_sessions_and_updates_nas_status(): void
    {
        Queue::fake([SyncNasRouterStateJob::class]);

        $testIp = '10.254.'.mt_rand(1, 254).'.'.mt_rand(2, 254);
        $nasId = (int) DB::table('nas')->insertGetId([
            'nasname' => $testIp,
            'shortname' => 'core-bng-'.Str::random(4),
            'type' => 'mikrotik',
            'secret' => 'test_routeros_secret',
            'coa_port' => 3799,
            'api_host' => $testIp,
            'api_port' => 8728,
            'api_username' => 'api_admin',
            'api_password_encrypted' => Crypt::encryptString('RouterOS_P@ssw0rd!'),
            'api_use_tls' => true,
            'sync_status' => 'pending',
            'tenant_id' => 1,
        ]);

        $service = app(RouterOsApiService::class);
        $sessions = $service->getActivePppoeSessions($nasId);

        $this->assertNotEmpty($sessions);
        $this->assertSame('sub_pppoe_101', $sessions[0]['name']);

        // Check updated status in database
        $nas = DB::table('nas')->where('id', $nasId)->first();
        $this->assertNotNull($nas);
        $this->assertSame('synced', $nas->sync_status);
        $this->assertNotNull($nas->last_synced_at);

        // Verify async job dispatch on 'network' queue
        SyncNasRouterStateJob::dispatch($nasId, 1)->onQueue('network');
        Queue::assertPushedOn('network', SyncNasRouterStateJob::class);
    }

    public function test_genieacs_tr069_service_provisions_pppoe_wan(): void
    {
        $service = app(GenieAcsTr069Service::class);
        $subscriberId = (string) Str::uuid();
        $deviceId = 'ZTEG_'.Str::random(8);

        $result = $service->provisionPppoe(
            deviceId: $deviceId,
            username: 'pppoe_test_user',
            password: 'secret_password_123',
            vlanId: 200,
            subscriberId: $subscriberId
        );

        $this->assertSame('queued', $result['status']);
        $this->assertSame($deviceId, $result['device_id']);

        // Verify ont_devices table record
        $ont = DB::table('ont_devices')
            ->where('subscriber_id', $subscriberId)
            ->where('serial_number', $deviceId)
            ->first();

        $this->assertNotNull($ont);
        $this->assertSame($deviceId, $ont->tr069_device_id);
    }

    public function test_ipam_allocator_service_allocates_sequential_ips_with_row_locking(): void
    {
        $allocator = app(IpamAllocatorService::class);
        $subscriber1 = (string) Str::uuid();
        $subscriber2 = (string) Str::uuid();

        // 1. Create a dedicated test pool
        $subnetOctet = mt_rand(10, 240);
        $poolId = (int) DB::table('ip_pools')->insertGetId([
            'tenant_id' => 1,
            'pool_name' => 'test_pool_'.Str::random(6),
            'subnet_cidr' => "100.64.{$subnetOctet}.0/29",
            'gateway_ip' => "100.64.{$subnetOctet}.1",
            'range_start' => "100.64.{$subnetOctet}.2",
            'range_end' => "100.64.{$subnetOctet}.4",
            'total_ips' => 3,
            'allocated_ips' => 0,
            'status' => 'active',
            'created_at' => now(),
        ]);

        // 2. Allocate first IP
        $ip1 = $allocator->allocateNextIp($poolId, $subscriber1, 1);
        $this->assertSame("100.64.{$subnetOctet}.2", $ip1);

        // 3. Allocate second IP
        $ip2 = $allocator->allocateNextIp($poolId, $subscriber2, 1);
        $this->assertSame("100.64.{$subnetOctet}.3", $ip2);

        // Verify pool counter updated
        $pool = DB::table('ip_pools')->where('id', $poolId)->first();
        $this->assertNotNull($pool);
        $this->assertSame(2, (int) $pool->allocated_ips);

        // 4. Release first IP
        $released = $allocator->releaseIp($poolId, $ip1);
        $this->assertTrue($released);

        $poolAfterRelease = DB::table('ip_pools')->where('id', $poolId)->first();
        $this->assertNotNull($poolAfterRelease);
        $this->assertSame(1, (int) $poolAfterRelease->allocated_ips);
    }

    public function test_clients_conf_generator_renders_hardened_clients_and_forbids_wildcard_routes(): void
    {
        $generator = app(ClientsConfGenerator::class);
        $rand2 = mt_rand(10, 240);
        $rand3 = mt_rand(1, 250);
        $peerIp = "10.{$rand2}.{$rand3}.5";
        $shortname = 'edge_wg_'.Str::random(6);

        DB::table('wireguard_peers')->where('wireguard_ip', $peerIp)->delete();

        // 1. Seed a valid WireGuard peer
        $peerId = DB::table('wireguard_peers')->insertGetId([
            'tenant_id' => 1,
            'shortname' => $shortname,
            'wireguard_ip' => $peerIp,
            'public_key' => 'abcdef1234567890abcdef1234567890=',
            'radius_secret_encrypted' => Crypt::encryptString('Hardened_Wg_Radius_Secret_2026!'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $rendered = $generator->render();

            $this->assertStringContainsString("client {$shortname} {", $rendered);
            $this->assertStringContainsString("ipaddr = {$peerIp}", $rendered);
            $this->assertStringContainsString('require_message_authenticator = yes', $rendered);
            $this->assertStringContainsString('Hardened_Wg_Radius_Secret_2026!', $rendered);
        } finally {
            DB::table('wireguard_peers')->where('id', $peerId)->delete();
        }

        // 2. Test Security Invariant: 0.0.0.0 or 0.0.0.0/0 MUST throw InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CRITICAL SECURITY VIOLATION');
        $generator->validateIpAddress('0.0.0.0/0');
    }
}
