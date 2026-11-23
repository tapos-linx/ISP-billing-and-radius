<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Olt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OnboardPhysicalHardwareTest extends TestCase
{
    use DatabaseTransactions;

    public function test_onboard_physical_mikrotik_router(): void
    {
        $exitCode = Artisan::call('carrier:onboard-hardware', [
            '--type' => 'mikrotik',
            '--name' => 'CCR2004-TEST-EDGE',
            '--ip' => '10.200.1.1',
            '--secret' => 'PhysicalSecret@99',
            '--server-ip' => '10.200.1.254',
            '--tenant-id' => 1,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('CARRIER HARDWARE ONBOARDING: CCR2004-TEST-EDGE (mikrotik)', $output);
        $this->assertStringContainsString('Registering BNG in Citus \'nas\' table', $output);
        $this->assertStringContainsString('Generating Production MikroTik RouterOS v7 Setup Commands', $output);
        $this->assertStringContainsString('HARDWARE ONBOARDING COMPLETE: CCR2004-TEST-EDGE READY FOR CARRIER TRAFFIC', $output);

        // Verify database entry in 'nas'
        $nas = DB::table('nas')->where('nasname', '10.200.1.1')->first();
        $this->assertNotNull($nas);
        $this->assertSame('CCR2004-TEST-EDGE', $nas->shortname);
        $this->assertSame('mikrotik', $nas->type);
        $this->assertSame('PhysicalSecret@99', $nas->secret);
        $this->assertSame(3799, $nas->coa_port);
    }

    public function test_onboard_physical_gpon_olt(): void
    {
        $exitCode = Artisan::call('carrier:onboard-hardware', [
            '--type' => 'olt',
            '--name' => 'OLT-HUAWEI-MA5608T',
            '--ip' => '10.200.2.1',
            '--vendor' => 'huawei',
            '--server-ip' => '10.200.2.254',
            '--tenant-id' => 1,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('CARRIER HARDWARE ONBOARDING: OLT-HUAWEI-MA5608T (olt)', $output);
        $this->assertStringContainsString('Registering GPON OLT in Citus \'olts\' table', $output);
        $this->assertStringContainsString('HUAWEI GPON OLT CONFIGURATION DIRECTIVE', $output);
        $this->assertStringContainsString('HARDWARE ONBOARDING COMPLETE', $output);

        // Verify database entry in 'olts'
        $olt = Olt::withoutGlobalScopes()->where('ip_address', '10.200.2.1')->first();
        $this->assertNotNull($olt);
        $this->assertSame('OLT-HUAWEI-MA5608T', $olt->name);
        $this->assertSame('huawei', $olt->vendor);
        $this->assertSame('online', $olt->status);
    }

    public function test_rejects_wildcard_ip_address(): void
    {
        $exitCode = Artisan::call('carrier:onboard-hardware', [
            '--type' => 'mikrotik',
            '--ip' => '0.0.0.0',
            '--secret' => 'SecretPass#123',
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('SECURITY VIOLATION', $output);
    }
}
