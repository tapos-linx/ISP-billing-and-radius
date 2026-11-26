<?php

declare(strict_types=1);

namespace Tests\Unit\Cpe;

use App\Services\Cpe\GenieAcsClient;
use App\Services\Cpe\OpticalPowerNormalizer;
use PHPUnit\Framework\TestCase;

class OpticalPowerNormalizerTest extends TestCase
{
    public function test_normalizes_huawei_hundredths_and_tenths_dbm(): void
    {
        // Huawei hundredths (-2150 = -21.50 dBm)
        $resHundredths = OpticalPowerNormalizer::normalize(-2150, 'huawei');
        $this->assertSame(-21.50, $resHundredths['normalized_dbm']);
        $this->assertSame('optimal', $resHundredths['status']);
        $this->assertTrue($resHundredths['is_healthy']);
        $this->assertTrue($resHundredths['is_in_range']);

        // Huawei tenths (-215 = -21.50 dBm)
        $resTenths = OpticalPowerNormalizer::normalize(-215, 'huawei');
        $this->assertSame(-21.50, $resTenths['normalized_dbm']);
        $this->assertSame('optimal', $resTenths['status']);

        // Huawei direct string with unit
        $resString = OpticalPowerNormalizer::normalize('-19.4 dBm', 'huawei');
        $this->assertSame(-19.40, $resString['normalized_dbm']);
        $this->assertTrue($resString['is_healthy']);
    }

    public function test_normalizes_zte_milli_and_tenths_dbm(): void
    {
        // ZTE milli-dBm (-21500 = -21.50 dBm)
        $resMilli = OpticalPowerNormalizer::normalize(-21500, 'zte');
        $this->assertSame(-21.50, $resMilli['normalized_dbm']);
        $this->assertSame('optimal', $resMilli['status']);

        // ZTE tenths (-228 = -22.80 dBm)
        $resTenths = OpticalPowerNormalizer::normalize(-228, 'zte');
        $this->assertSame(-22.80, $resTenths['normalized_dbm']);
        $this->assertTrue($resTenths['is_healthy']);
    }

    public function test_normalizes_fiberhome_microwatts_to_dbm(): void
    {
        // 7.0794578 uW converts to ~ -21.50 dBm
        $resMicrowatts = OpticalPowerNormalizer::normalize(7.0794578, 'fiberhome');
        $this->assertSame(-21.50, $resMicrowatts['normalized_dbm']);
        $this->assertSame('optimal', $resMicrowatts['status']);

        // String with uW suffix
        $resString = OpticalPowerNormalizer::normalize('10.0 uW', 'fiberhome');
        // 10 uW = -20.00 dBm
        $this->assertSame(-20.00, $resString['normalized_dbm']);
        $this->assertTrue($resString['is_healthy']);

        // Zero or negative microwatts (Loss of Signal)
        $resZero = OpticalPowerNormalizer::normalize(0, 'fiberhome');
        $this->assertSame(-40.00, $resZero['normalized_dbm']);
        $this->assertSame('out_of_range', $resZero['status']);
        $this->assertFalse($resZero['is_in_range']);
    }

    public function test_normalizes_vsol_and_cdata_tenths_dbm(): void
    {
        // VSOL tenths (-234 = -23.40 dBm)
        $res = OpticalPowerNormalizer::normalize(-234, 'vsol');
        $this->assertSame(-23.40, $res['normalized_dbm']);
        $this->assertSame('optimal', $res['status']);
        $this->assertTrue($res['is_healthy']);
    }

    public function test_flags_warning_and_critical_optical_attenuation(): void
    {
        // Warning: between -27.01 and -30.00 dBm
        $resWarning = OpticalPowerNormalizer::normalize(-28.50);
        $this->assertSame(-28.50, $resWarning['normalized_dbm']);
        $this->assertSame('warning', $resWarning['status']);
        $this->assertFalse($resWarning['is_healthy']);
        $this->assertTrue($resWarning['is_in_range']);

        // Critical: between -30.01 and -32.00 dBm
        $resCritical = OpticalPowerNormalizer::normalize(-31.20);
        $this->assertSame(-31.20, $resCritical['normalized_dbm']);
        $this->assertSame('critical', $resCritical['status']);
        $this->assertFalse($resCritical['is_healthy']);
        $this->assertTrue($resCritical['is_in_range']);
    }

    public function test_flags_out_of_range_saturation_and_loss_of_signal(): void
    {
        // Optical saturation (> -8.0 dBm, e.g. -5.0 dBm)
        $resSaturation = OpticalPowerNormalizer::normalize(-5.0);
        $this->assertSame(-5.00, $resSaturation['normalized_dbm']);
        $this->assertSame('out_of_range', $resSaturation['status']);
        $this->assertFalse($resSaturation['is_healthy']);
        $this->assertFalse($resSaturation['is_in_range']);

        // Loss of signal (< -32.0 dBm, e.g. -36.5 dBm)
        $resLos = OpticalPowerNormalizer::normalize(-36.5);
        $this->assertSame(-36.50, $resLos['normalized_dbm']);
        $this->assertSame('out_of_range', $resLos['status']);
        $this->assertFalse($resLos['is_in_range']);
    }

    public function test_genieacs_client_delegates_to_normalizer(): void
    {
        $client = new GenieAcsClient('http://127.0.0.1:7557');
        $res = $client->normalizeOpticalPower(-1950, 'huawei');

        $this->assertSame(-19.50, $res['normalized_dbm']);
        $this->assertSame('optimal', $res['status']);
        $this->assertTrue($res['is_healthy']);
    }
}
