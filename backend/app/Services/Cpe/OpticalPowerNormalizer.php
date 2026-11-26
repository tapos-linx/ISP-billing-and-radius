<?php

declare(strict_types=1);

namespace App\Services\Cpe;

/**
 * Multi-Vendor TR-069 Optical Power Normalizer
 *
 * Normalizes raw ONU optical Rx/Tx power telemetry across Huawei, ZTE,
 * FiberHome, and VSOL/C-Data CWMP implementations into standard floating-point dBm.
 * Evaluates optical link health and flags out-of-range values (-8.0 dBm to -32.0 dBm).
 */
class OpticalPowerNormalizer
{
    public const MIN_OPTICAL_HEALTHY_DBM = -27.0;

    public const MAX_OPTICAL_HEALTHY_DBM = -8.0;

    public const MIN_OPERATIONAL_DBM = -32.0;

    public const MAX_OPERATIONAL_DBM = -8.0;

    /**
     * Normalize raw optical power reading into standard floating-point dBm.
     *
     * @param  int|float|string  $rawPower  Raw value from TR-069 parameter
     * @param  string|null  $vendor  Vendor hint ('huawei', 'zte', 'fiberhome', 'vsol')
     * @return array{
     *     normalized_dbm: float,
     *     status: string,
     *     is_healthy: bool,
     *     is_in_range: bool,
     *     vendor: string,
     *     raw_value: int|float|string
     * }
     */
    public static function normalize(int|float|string $rawPower, ?string $vendor = null): array
    {
        $detectedVendor = strtolower(trim($vendor ?? 'generic'));
        $cleanStr = is_string($rawPower) ? trim($rawPower) : (string) $rawPower;

        $normalizedDbm = self::parseToDbm($rawPower, $cleanStr, $detectedVendor);
        $normalizedDbm = round($normalizedDbm, 2);

        $status = self::classifyStatus($normalizedDbm);
        $isHealthy = $status === 'optimal';
        $isInRange = $status !== 'out_of_range';

        return [
            'normalized_dbm' => $normalizedDbm,
            'status' => $status,
            'is_healthy' => $isHealthy,
            'is_in_range' => $isInRange,
            'vendor' => $detectedVendor,
            'raw_value' => $rawPower,
        ];
    }

    /**
     * Parse raw value into dBm based on vendor format conventions.
     */
    protected static function parseToDbm(int|float|string $raw, string $cleanStr, string $vendor): float
    {
        // 1. Check for explicit unit suffix in string
        if (preg_match('/([+-]?\d+(?:\.\d+)?)\s*(?:uw|µw|microwatt)/i', $cleanStr, $matches)) {
            $uW = (float) $matches[1];

            return self::microWattsToDbm($uW);
        }

        if (preg_match('/([+-]?\d+(?:\.\d+)?)\s*dbm/i', $cleanStr, $matches)) {
            return (float) $matches[1];
        }

        $numericVal = is_numeric($cleanStr) ? (float) $cleanStr : -40.0;

        // 2. FiberHome / TR-181 Microwatts conversion
        if ($vendor === 'fiberhome') {
            if ($numericVal > 0) {
                return self::microWattsToDbm($numericVal);
            }
            if ($numericVal === 0.0) {
                return -40.0; // 0 uW optical power = Loss of Signal (LOS)
            }
            if ($numericVal <= -100) {
                return $numericVal / 100.0;
            }

            return $numericVal;
        }

        // 3. ZTE: Milli-dBm (0.001 dBm) or tenths
        if ($vendor === 'zte') {
            if (abs($numericVal) >= 10000) {
                return $numericVal / 1000.0;
            }
            if (abs($numericVal) >= 1000) {
                return $numericVal / 100.0;
            }
            if (abs($numericVal) >= 100) {
                return $numericVal / 10.0;
            }

            return $numericVal;
        }

        // 4. Huawei: Hundredths (0.01 dBm) or tenths (0.1 dBm)
        if ($vendor === 'huawei') {
            if (abs($numericVal) >= 1000) {
                return $numericVal / 100.0;
            }
            if (abs($numericVal) >= 100) {
                return $numericVal / 10.0;
            }

            return $numericVal;
        }

        // 5. VSOL / C-Data: Tenths of dBm
        if ($vendor === 'vsol' || $vendor === 'cdata') {
            if (abs($numericVal) >= 1000) {
                return $numericVal / 100.0;
            }
            if (abs($numericVal) >= 100) {
                return $numericVal / 10.0;
            }

            return $numericVal;
        }

        // 6. Generic Auto-detection:
        // Optical Rx is negative; if positive between 0.001 and 500, likely microwatts
        if ($numericVal > 0 && $numericVal <= 500) {
            return self::microWattsToDbm($numericVal);
        }

        if (abs($numericVal) >= 10000) {
            return $numericVal / 1000.0;
        }
        if (abs($numericVal) >= 1000) {
            return $numericVal / 100.0;
        }
        if (abs($numericVal) >= 100) {
            return $numericVal / 10.0;
        }

        return $numericVal;
    }

    /**
     * Convert optical microwatts (uW) to dBm: P(dBm) = 10 * log10(P(uW) / 1000)
     */
    public static function microWattsToDbm(float $microWatts): float
    {
        if ($microWatts <= 0) {
            return -40.0; // Loss of Signal (LOS)
        }

        return 10.0 * log10($microWatts / 1000.0);
    }

    /**
     * Classify optical link status against carrier GPON budget.
     */
    protected static function classifyStatus(float $dbm): string
    {
        // Out of range: Over-saturation (> -8.0 dBm) or Loss of Signal / severe attenuation (< -32.0 dBm)
        if ($dbm > self::MAX_OPERATIONAL_DBM || $dbm < self::MIN_OPERATIONAL_DBM) {
            return 'out_of_range';
        }

        // Optimal operating window (-8.0 dBm down to -27.0 dBm)
        if ($dbm >= self::MIN_OPTICAL_HEALTHY_DBM) {
            return 'optimal';
        }

        // High attenuation warning (-27.01 dBm down to -30.0 dBm)
        if ($dbm >= -30.0) {
            return 'warning';
        }

        // Critical near-drop threshold (-30.01 dBm down to -32.0 dBm)
        return 'critical';
    }
}
