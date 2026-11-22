<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Services\Compliance\CgnatPbaExportService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GroupDComplianceAndBackupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.testing' => true]);
    }

    public function test_cgnat_pba_service_allocates_and_resolves_subscriber_for_lawful_intercept(): void
    {
        $service = app(CgnatPbaExportService::class);
        $subscriberId = (string) Str::uuid();
        $username = 'cgnat_user_'.Str::random(6);

        // 1. Seed subscriber
        DB::table('subscribers')->insert([
            'subscriber_id' => $subscriberId,
            'tenant_id' => 1,
            'username' => $username,
            'status' => 'active',
            'expires_at' => now()->addMonth(),
            'created_at' => now(),
        ]);

        // 2. Allocate Port Block: dynamic public IP, ports 15000 to 15999
        $randOctet = mt_rand(10, 240);
        $publicIp = "103.145.{$randOctet}.5";
        $privateIp = '100.64.12.34';
        $portStart = 15000;
        $portEnd = 15999;

        $logId = $service->logPortBlockAllocation(
            subscriberId: $subscriberId,
            privateIp: $privateIp,
            publicIp: $publicIp,
            portStart: $portStart,
            portEnd: $portEnd,
            tenantId: 1
        );

        $this->assertGreaterThan(0, $logId);

        // 3. Lawful Intercept Query: Who had 103.145.10.5:15432 right now?
        $resolved = $service->resolveSubscriber(
            publicIp: $publicIp,
            port: 15432,
            timestamp: now()
        );

        $this->assertNotNull($resolved);
        $this->assertSame($subscriberId, (string) $resolved['subscriber_id']);
        $this->assertSame($username, $resolved['username']);
        $this->assertSame($privateIp, $resolved['private_ip']);

        // 4. Test Query for unallocated port: 103.145.10.5:25000 should return null
        $unresolved = $service->resolveSubscriber(
            publicIp: $publicIp,
            port: 25000,
            timestamp: now()
        );

        $this->assertNull($unresolved);

        // 5. Export CSV
        $csv = $service->exportBtrcPbaAuditCsv(
            startDate: now()->subDay()->toDateTimeString(),
            endDate: now()->addDay()->toDateTimeString(),
            tenantId: 1
        );

        $this->assertStringContainsString('allocated_at,deallocated_at,public_ip,port_start,port_end', $csv);
        $this->assertStringContainsString($publicIp, $csv);
        $this->assertStringContainsString($username, $csv);
    }

    public function test_run_encrypted_backup_command_executes_and_records_audit(): void
    {
        $exitCode = Artisan::call('ops:backup-database', ['--dry-run' => true]);
        $this->assertSame(0, $exitCode);

        // Verify backup_runs table record
        $latestRun = DB::table('backup_runs')->orderByDesc('id')->first();
        $this->assertNotNull($latestRun);
        $this->assertSame('completed', $latestRun->status);
        $this->assertNotEmpty($latestRun->checksum_sha256);
        $this->assertGreaterThan(0, (int) $latestRun->bytes_size);
        $this->assertFileExists((string) $latestRun->file_path);
    }
}
