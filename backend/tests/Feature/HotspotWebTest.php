<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HotspotWebTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! DB::table('hotspot_profiles')->where('profile_id', 99)->exists()) {
            DB::table('hotspot_profiles')->insert([
                'profile_id' => 99,
                'tenant_id' => 1,
                'name' => 'Test 1 Hour Pass',
                'validity_seconds' => 3600,
                'data_limit_bytes' => 1073741824,
                'rate_limit_down_kbps' => 10240,
                'rate_limit_up_kbps' => 10240,
                'price_bdt' => 25.00,
                'simultaneous_use' => 1,
                'created_at' => now(),
            ]);
        }
    }

    public function test_hotspot_captive_portal_page_loads_successfully(): void
    {
        $response = $this->get('/hotspot/login');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Hotspot/CaptivePortal')
            ->where('hotspotName', 'Carrier Gigabit Public Wi-Fi')
        );
    }

    public function test_hotspot_voucher_print_batch_loads_successfully(): void
    {
        $batchId = 'BATCH-POS-TEST';
        $vchId = (string) Str::uuid();

        DB::table('hotspot_vouchers')->insert([
            'voucher_id' => $vchId,
            'tenant_id' => 1,
            'profile_id' => 99,
            'batch_id' => $batchId,
            'code' => 'TEST-POS-'.Str::upper(Str::random(6)),
            'pin' => '9988',
            'status' => 'GENERATED',
            'time_limit_seconds' => 86400,
            'data_limit_bytes' => 5368709120,
            'used_time_seconds' => 0,
            'used_bytes' => 0,
            'rate_limit_down_kbps' => 20480,
            'rate_limit_up_kbps' => 20480,
            'price_bdt' => 60.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get("/hotspot/print/{$batchId}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Hotspot/VoucherPrint')
            ->where('batchId', $batchId)
        );
    }

    public function test_hotspot_single_voucher_print_loads_successfully(): void
    {
        $code = 'VCH-'.Str::upper(Str::random(8));
        $vchId = (string) Str::uuid();

        DB::table('hotspot_vouchers')->insert([
            'voucher_id' => $vchId,
            'tenant_id' => 1,
            'profile_id' => 99,
            'batch_id' => 'BATCH-SINGLE',
            'code' => $code,
            'pin' => '1234',
            'status' => 'GENERATED',
            'time_limit_seconds' => 1800,
            'data_limit_bytes' => 524288000,
            'used_time_seconds' => 0,
            'used_bytes' => 0,
            'rate_limit_down_kbps' => 5120,
            'rate_limit_up_kbps' => 5120,
            'price_bdt' => 10.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get("/hotspot/print-voucher/{$code}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Hotspot/VoucherPrint')
        );
    }
}
